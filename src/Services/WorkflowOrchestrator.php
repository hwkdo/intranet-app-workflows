<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionRegistry;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Enums\HistoryWhat;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionAttempt;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionRun;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowHistory;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStep;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStepAction;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sequential action pipeline. Failures are marked only — the flow continues.
 * Assignee resolution is expected as the last step action (core.resolve_assignee).
 */
class WorkflowOrchestrator
{
    public function __construct(
        private readonly ActionRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $formData
     */
    public function startFlow(WorkflowType $type, int $initiatorId, array $formData = []): WorkflowFlow
    {
        return DB::transaction(function () use ($type, $initiatorId, $formData): WorkflowFlow {
            $firstStep = $type->steps()->orderBy('position')->firstOrFail();

            $flow = WorkflowFlow::query()->create([
                'type_id' => $type->id,
                'initiator_id' => $initiatorId,
                'current_step_position' => $firstStep->position,
                'status' => FlowStatus::Active,
                'payload' => $formData,
                'due_date' => $this->resolveDueDateFromPayload($formData),
            ]);

            WorkflowHistory::query()->create([
                'flow_id' => $flow->id,
                'step_id' => $firstStep->id,
                'user_id' => $initiatorId,
                'what' => HistoryWhat::Initiated,
                'data' => $formData,
            ]);

            // Completing the first step immediately runs its actions, then advances.
            return $this->submitStep($flow->fresh(['type.steps']), $formData, $initiatorId, skipHistoryMerge: true);
        });
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function submitStep(
        WorkflowFlow $flow,
        array $formData,
        int $userId,
        bool $skipHistoryMerge = false,
    ): WorkflowFlow {
        return DB::transaction(function () use ($flow, $formData, $userId, $skipHistoryMerge): WorkflowFlow {
            $flow->refresh();

            if (! $flow->status->isOpen()) {
                throw new \RuntimeException('Flow is not open for submission.');
            }

            $currentStep = $flow->currentStep();
            if (! $currentStep) {
                throw new \RuntimeException('Current step not found.');
            }

            // Current assignee is consuming this step; clear before pipeline.
            // Last action (resolve_assignee) sets who owns the next step.
            $flow->forceFill([
                'assignee_user_id' => null,
                'assignee_group_key' => null,
            ])->save();

            if (! $skipHistoryMerge) {
                $flow->payload = array_merge($flow->payload ?? [], $formData);
                if ($due = $this->resolveDueDateFromPayload($formData)) {
                    $flow->due_date = $due;
                }
                $flow->save();

                WorkflowHistory::query()->create([
                    'flow_id' => $flow->id,
                    'step_id' => $currentStep->id,
                    'user_id' => $userId,
                    'what' => HistoryWhat::Submitted,
                    'data' => $formData,
                ]);
            } else {
                // startFlow already wrote initiated history + payload
                $flow->payload = array_merge($flow->payload ?? [], $formData);
                if ($due = $this->resolveDueDateFromPayload($formData)) {
                    $flow->due_date = $due;
                }
                $flow->save();
            }

            $this->materializeAndRunStepActions($flow, $currentStep);

            $flow->refresh();

            $nextStep = $flow->type
                ->steps()
                ->where('position', '>', $currentStep->position)
                ->orderBy('position')
                ->first();

            if ($nextStep) {
                $flow->forceFill([
                    'current_step_position' => $nextStep->position,
                    'status' => FlowStatus::Active,
                ])->save();
            } else {
                $flow->forceFill([
                    'status' => FlowStatus::Completed,
                    'finished_at' => now(),
                    'assignee_user_id' => null,
                    'assignee_group_key' => null,
                ])->save();
            }

            $this->refreshErrorSummary($flow);

            return $flow->fresh(['type.steps', 'actionRuns', 'histories']);
        });
    }

    public function retryActionRun(WorkflowActionRun $run, bool $ignoreDueDate = false): WorkflowActionRun
    {
        $run->loadMissing(['flow', 'stepAction.action']);

        if ($run->status === ActionRunStatus::Succeeded || $run->status === ActionRunStatus::Skipped) {
            throw new \RuntimeException('Cannot retry a successful or skipped action run.');
        }

        $run->forceFill([
            'status' => ActionRunStatus::Pending,
            'waiting_until' => null,
            'finished_at' => null,
            'latest_message' => null,
        ])->save();

        $this->executeRun($run->fresh(['flow', 'stepAction.action']), $ignoreDueDate);
        $this->refreshErrorSummary($run->flow->fresh());

        return $run->fresh(['attempts']);
    }

    public function processWaitingRuns(?Carbon $now = null): int
    {
        $now ??= now();
        $processed = 0;

        $runs = WorkflowActionRun::query()
            ->where('status', ActionRunStatus::Waiting)
            ->whereNotNull('waiting_until')
            ->where('waiting_until', '<=', $now)
            ->with(['flow.type.steps', 'stepAction.action'])
            ->orderBy('id')
            ->get();

        foreach ($runs as $run) {
            $run->forceFill([
                'status' => ActionRunStatus::Pending,
                'waiting_until' => null,
            ])->save();

            $this->executeRun($run->fresh(['flow', 'stepAction.action']), ignoreDueDate: false);
            $run->refresh();

            if ($run->status->isTerminal()) {
                $this->continueStepPipelineAfter($run);
            }

            $this->refreshErrorSummary($run->flow->fresh());
            $processed++;
        }

        return $processed;
    }

    private function materializeAndRunStepActions(WorkflowFlow $flow, WorkflowStep $step): void
    {
        $bindings = $step->stepActions()->with('action')->orderBy('position')->get();

        foreach ($bindings as $binding) {
            WorkflowActionRun::query()->firstOrCreate(
                [
                    'flow_id' => $flow->id,
                    'step_action_id' => $binding->id,
                ],
                [
                    'status' => ActionRunStatus::Pending,
                    'position' => $binding->position,
                ],
            );
        }

        $runs = WorkflowActionRun::query()
            ->where('flow_id', $flow->id)
            ->whereIn('step_action_id', $bindings->pluck('id'))
            ->with(['flow', 'stepAction.action'])
            ->orderBy('position')
            ->get();

        foreach ($runs as $run) {
            if ($run->status->isTerminal()) {
                continue;
            }

            $this->executeRun($run->fresh(['flow', 'stepAction.action']), ignoreDueDate: false);
            $run->refresh();

            // Due-date-Waiting blockiert spätere Actions; Handler-Waiting (z. B. x500-Poll) nicht.
            if ($run->status === ActionRunStatus::Waiting && $this->isDueDateWaiting($run)) {
                break;
            }
        }
    }

    /**
     * After a waiting run becomes terminal, execute later pending runs of the same step.
     */
    private function continueStepPipelineAfter(WorkflowActionRun $completedRun): void
    {
        $completedRun->loadMissing('stepAction');
        $stepId = $completedRun->stepAction?->step_id;
        if (! $stepId) {
            return;
        }

        $laterRuns = WorkflowActionRun::query()
            ->where('flow_id', $completedRun->flow_id)
            ->where('position', '>', $completedRun->position)
            ->whereHas('stepAction', fn ($q) => $q->where('step_id', $stepId))
            ->whereIn('status', [ActionRunStatus::Pending->value, ActionRunStatus::Waiting->value])
            ->with(['flow', 'stepAction.action'])
            ->orderBy('position')
            ->get();

        foreach ($laterRuns as $run) {
            if ($run->status === ActionRunStatus::Waiting && $this->isDueDateWaiting($run)) {
                break;
            }

            if ($run->status === ActionRunStatus::Waiting) {
                continue;
            }

            $this->executeRun($run->fresh(['flow', 'stepAction.action']), ignoreDueDate: false);
            $run->refresh();

            if ($run->status === ActionRunStatus::Waiting && $this->isDueDateWaiting($run)) {
                break;
            }
        }
    }

    private function executeRun(WorkflowActionRun $run, bool $ignoreDueDate): void
    {
        $flow = $run->flow;
        $binding = $run->stepAction;

        if (! $binding || ! $binding->action) {
            $this->finalizeRun($run, ActionResult::failed('Step action binding missing', retryable: false));

            return;
        }

        if ($binding->wait_for_due_date && ! $ignoreDueDate) {
            $due = $flow->due_date;
            if ($due && $due->copy()->startOfDay()->isFuture()) {
                $run->forceFill([
                    'status' => ActionRunStatus::Waiting,
                    'waiting_until' => $due->copy()->startOfDay(),
                    'latest_message' => 'Wartet auf Stichtag '.$due->format('d.m.Y'),
                ])->save();

                return;
            }
        }

        if (! $this->shouldRun($flow, $binding)) {
            $this->finalizeRun($run, ActionResult::skipped('run_when not matched'));

            return;
        }

        $attemptNo = (int) $run->attempts()->max('attempt_no') + 1;
        $attempt = WorkflowActionAttempt::query()->create([
            'action_run_id' => $run->id,
            'attempt_no' => $attemptNo,
            'status' => ActionRunStatus::Running,
            'idempotency_key' => sprintf('flow-%d-stepaction-%d-attempt-%d-%s', $flow->id, $binding->id, $attemptNo, Str::uuid()),
            'started_at' => now(),
        ]);

        $run->forceFill([
            'status' => ActionRunStatus::Running,
            'started_at' => $run->started_at ?? now(),
            'latest_message' => null,
        ])->save();

        $config = $binding->resolvedConfig();
        $flow->refresh();

        try {
            $handler = $this->registry->resolve($binding->action->handler_key);
            $context = new ActionContext(
                flow: $flow,
                stepAction: $binding,
                actionRun: $run,
                attempt: $attempt,
                payload: $flow->payload ?? [],
                config: $config,
            );
            $result = $handler->handle($context);
        } catch (Throwable $e) {
            report($e);
            $result = ActionResult::failed(
                message: $e->getMessage(),
                errors: [$e->getMessage()],
                retryable: true,
            );
            $attempt->exception = $e->getFile().':'.$e->getLine().' '.$e->getMessage();
        }

        $this->applyResult($run, $attempt, $result, $flow);
    }

    private function shouldRun(WorkflowFlow $flow, WorkflowStepAction $binding): bool
    {
        $runWhen = $binding->run_when;
        if (! is_array($runWhen) || $runWhen === []) {
            return true;
        }

        // Simple equality checks: ["field" => expected]
        foreach ($runWhen as $key => $expected) {
            if (($flow->payload[$key] ?? null) != $expected) {
                return false;
            }
        }

        return true;
    }

    private function applyResult(
        WorkflowActionRun $run,
        WorkflowActionAttempt $attempt,
        ActionResult $result,
        WorkflowFlow $flow,
    ): void {
        $attempt->forceFill([
            'status' => $result->status,
            'messages' => $result->messages,
            'errors' => $result->errors,
            'retryable' => $result->retryable,
            'finished_at' => now(),
        ])->save();

        if ($result->output !== []) {
            $flow->payload = array_merge($flow->payload ?? [], $result->output);
            $flow->save();

            $run->output = array_merge($run->output ?? [], $result->output);
        }

        if ($result->status === ActionRunStatus::Waiting) {
            $run->forceFill([
                'status' => ActionRunStatus::Waiting,
                'latest_message' => $result->message,
                'finished_at' => null,
                'waiting_until' => $result->waitingUntil,
            ])->save();

            return;
        }

        $run->forceFill([
            'status' => $result->status,
            'latest_message' => $result->message,
            'finished_at' => now(),
            'waiting_until' => null,
        ])->save();
    }

    private function isDueDateWaiting(WorkflowActionRun $run): bool
    {
        $run->loadMissing('stepAction');

        return (bool) ($run->stepAction?->wait_for_due_date);
    }

    private function finalizeRun(WorkflowActionRun $run, ActionResult $result): void
    {
        $attemptNo = (int) $run->attempts()->max('attempt_no') + 1;
        $attempt = WorkflowActionAttempt::query()->create([
            'action_run_id' => $run->id,
            'attempt_no' => $attemptNo,
            'status' => $result->status,
            'messages' => $result->messages,
            'errors' => $result->errors,
            'retryable' => $result->retryable,
            'idempotency_key' => sprintf('flow-%d-run-%d-attempt-%d-%s', $run->flow_id, $run->id, $attemptNo, Str::uuid()),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $run->forceFill([
            'status' => $result->status,
            'latest_message' => $result->message,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => now(),
        ])->save();

        unset($attempt);
    }

    private function refreshErrorSummary(WorkflowFlow $flow): void
    {
        $failed = $flow->actionRuns()
            ->whereIn('status', [ActionRunStatus::Failed->value, ActionRunStatus::Partial->value])
            ->orderBy('position')
            ->get(['latest_message', 'status', 'position']);

        if ($failed->isEmpty()) {
            $flow->forceFill(['error_summary' => null])->save();

            return;
        }

        $summary = $failed
            ->map(fn (WorkflowActionRun $run): string => sprintf(
                '#%d %s: %s',
                $run->position,
                $run->status->value,
                $run->latest_message ?? ''
            ))
            ->implode("\n");

        $flow->forceFill(['error_summary' => $summary])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDueDateFromPayload(array $payload): ?string
    {
        $austritt = $payload['austrittsdatum'] ?? null;
        if (is_string($austritt) && $austritt !== '') {
            try {
                return Carbon::parse($austritt)->addDay()->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        $raw = $payload['einsatzab'] ?? $payload['due_date'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
