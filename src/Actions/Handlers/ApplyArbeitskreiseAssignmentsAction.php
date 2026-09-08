<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use App\Models\Arbeitskreis;
use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Throwable;

final class ApplyArbeitskreiseAssignmentsAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.apply_arbeitskreise_assignments';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $leavingId = (int) $mitarbeiterId;
        $assignments = $context->payloadValue('arbeitskreise_assignments');
        if (! is_array($assignments) || $assignments === []) {
            return ActionResult::succeeded('Keine Arbeitskreis-Zuweisungen');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Arbeitskreis-Zuweisungen anwenden ('.count($assignments).')',
            output: ['arbeitskreise_assignments_planned' => $assignments],
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];

        foreach ($assignments as $key => $row) {
            if (! is_array($row)) {
                $errors[] = "{$key}: ungültiger Eintrag";

                continue;
            }

            $akId = (int) ($row['ak_id'] ?? 0);
            $role = (string) ($row['role'] ?? '');
            $toUserId = $row['to_user_id'] ?? null;
            $action = (string) ($row['action'] ?? '');

            if ($akId < 1 || ! in_array($role, ['vertreter', 'stv'], true)) {
                $errors[] = "{$key}: ak_id/role ungültig";

                continue;
            }

            try {
                $ak = Arbeitskreis::query()->find($akId);
                if (! $ak) {
                    $errors[] = "{$key}: Arbeitskreis #{$akId} nicht gefunden";

                    continue;
                }

                $relation = $role === 'vertreter' ? $ak->vertreter() : $ak->stv();

                if (is_numeric($toUserId) && (int) $toUserId > 0 && $action !== 'remove') {
                    $relation->syncWithoutDetaching([(int) $toUserId]);
                    $messages[] = "AK #{$akId} {$role}: attach User #{$toUserId}";
                }

                $relation->detach($leavingId);
                $messages[] = "AK #{$akId} {$role}: detach User #{$leavingId}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$key}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Arbeitskreis-Zuweisungen fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Arbeitskreise teilweise zugewiesen',
                messages: $messages,
                errors: $errors,
            );
        }

        return ActionResult::succeeded(
            message: 'Arbeitskreis-Zuweisungen angewendet',
            messages: $messages,
        );
    }
}
