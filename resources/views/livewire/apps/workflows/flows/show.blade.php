<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionRun;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Services\FlowClaimService;
use Hwkdo\IntranetAppWorkflows\Services\MaNeuStep3Planner;
use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Hwkdo\IntranetAppWorkflows\Support\StepFormRules;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, state, title};

state([
    'flowId' => null,
    /** @var array<string, mixed> */
    'form' => [],
    'usernameAvailable' => null,
    'usernameChecked' => false,
    /** @var list<string> */
    'shareGroupsSelected' => [],
    /** @var list<string> */
    'emailGroupsSelected' => [],
    /** @var list<string> */
    'd3GroupsSelected' => [],
    /** @var list<string> */
    'shareGroupsAvailable' => [],
    /** @var list<string> */
    'emailGroupsAvailable' => [],
    /** @var list<string> */
    'd3GroupsAvailable' => [],
    'ldapAnalogError' => null,
]);

$resetFormFromCurrentStep = function (MaNeuStep3Planner $planner): void {
    $flow = WorkflowFlow::query()->with(['type.steps.inputs'])->findOrFail($this->flowId);
    $step = $flow->currentStep();

    $this->form = $step
        ? StepFormRules::initialFormForStep($flow, $step)
        : [];

    $this->prepareItBenutzerStep($planner);
};

$prepareItBenutzerStep = function (MaNeuStep3Planner $planner): void {
    $flow = WorkflowFlow::query()->findOrFail($this->flowId);
    $step = $flow->currentStep();

    if ($step?->key !== 'it_benutzer') {
        $this->shareGroupsSelected = [];
        $this->emailGroupsSelected = [];
        $this->d3GroupsSelected = [];
        $this->shareGroupsAvailable = [];
        $this->emailGroupsAvailable = [];
        $this->d3GroupsAvailable = [];
        $this->ldapAnalogError = null;
        $this->usernameAvailable = null;
        $this->usernameChecked = false;

        return;
    }

    $analogUserId = $flow->getPayloadValue('laufwerke_analog_zu');
    $analogUserId = is_numeric($analogUserId) ? (int) $analogUserId : null;
    $analog = $planner->analogGroups($analogUserId);

    $this->shareGroupsAvailable = $analog['share'];
    $this->emailGroupsAvailable = $analog['email'];
    $this->d3GroupsAvailable = $analog['d3'];
    $this->ldapAnalogError = $analog['error'];

    $existing = $flow->getPayloadValue('add_ldap_groups');
    $existingList = is_array($existing)
        ? array_values(array_filter($existing, static fn ($g): bool => is_string($g) && $g !== ''))
        : (is_string($existing) && $existing !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $existing))))
            : []);

    if ($existingList !== []) {
        $this->shareGroupsSelected = array_values(array_intersect($existingList, $this->shareGroupsAvailable));
        $this->emailGroupsSelected = array_values(array_intersect($existingList, $this->emailGroupsAvailable));
        $this->d3GroupsSelected = array_values(array_intersect($existingList, $this->d3GroupsAvailable));
    } else {
        $this->shareGroupsSelected = [];
        $this->emailGroupsSelected = [];
        $this->d3GroupsSelected = [];
    }

    if (blank($this->form['username'] ?? null)) {
        $guess = $planner->suggestUsername();
        if ($guess !== null) {
            $this->form['username'] = $guess;
            $this->usernameAvailable = $planner->isUsernameAvailable($guess);
            $this->usernameChecked = true;
        }
    } elseif (filled($this->form['username'] ?? null) && ! $this->usernameChecked) {
        $this->usernameAvailable = $planner->isUsernameAvailable((string) $this->form['username']);
        $this->usernameChecked = true;
    }
};

mount(function (WorkflowFlow $flow, MaNeuStep3Planner $planner): void {
    abort_unless(FlowAccess::canView($flow, Auth::user()), 403);

    $this->flowId = $flow->id;
    $this->resetFormFromCurrentStep($planner);
});

$flow = computed(fn () => WorkflowFlow::query()
    ->with([
        'type.steps.inputs',
        'type.steps.stepActions.action',
        'actionRuns.stepAction.action',
        'actionRuns.stepAction.step',
        'actionRuns.attempts',
        'histories',
        'initiator',
        'assignee',
    ])
    ->findOrFail($this->flowId));

$pageTitle = computed(function (): string {
    $flow = $this->flow;
    $name = trim(($flow->getPayloadValue('vorname') ?? '').' '.($flow->getPayloadValue('nachname') ?? ''));

    return $name !== '' ? 'Workflow: '.$name : 'Workflow #'.$flow->id;
});

title('Workflow-Detail');

$currentStep = computed(fn () => $this->flow->currentStep());

$canEdit = computed(fn (): bool => FlowAccess::canEdit($this->flow, Auth::user()));

$isCompleted = computed(fn (): bool => $this->flow->status === FlowStatus::Completed);

$isItBenutzerStep = computed(fn (): bool => $this->currentStep?->key === 'it_benutzer');

$forcedLdapGroups = computed(function (): array {
    if (! $this->isItBenutzerStep) {
        return [];
    }

    return app(MaNeuStep3Planner::class)->forcedGroups($this->flow);
});

$liveFieldKeys = computed(function (): array {
    $step = $this->currentStep;

    return $step ? StepFormRules::liveDependencyKeys($step->inputs) : [];
});

$runsForStep = function (int $stepId) {
    return $this->flow->actionRuns
        ->filter(fn (WorkflowActionRun $run): bool => (int) $run->stepAction?->step_id === $stepId)
        ->sortBy('position')
        ->values();
};

$suggestUsername = function (MaNeuStep3Planner $planner): void {
    $guess = $planner->suggestUsername();
    if ($guess === null) {
        Flux::toast(variant: 'warning', text: 'Kein Username-Vorschlag möglich (LDAP?).');

        return;
    }

    $this->form['username'] = $guess;
    $this->usernameAvailable = $planner->isUsernameAvailable($guess);
    $this->usernameChecked = true;
};

$checkUsernameAvailability = function (MaNeuStep3Planner $planner): void {
    $username = trim((string) ($this->form['username'] ?? ''));
    if ($username === '') {
        $this->addError('form.username', 'Username ist erforderlich.');

        return;
    }

    $this->usernameAvailable = $planner->isUsernameAvailable($username);
    $this->usernameChecked = true;

    if ($this->usernameAvailable === null) {
        Flux::toast(variant: 'warning', text: 'LDAP-Prüfung fehlgeschlagen.');
    }
};

$updatedForm = function (mixed $value, ?string $key = null): void {
    if ($key === 'username') {
        $this->usernameAvailable = null;
        $this->usernameChecked = false;
    }
};

$submit = function (WorkflowOrchestrator $orchestrator, MaNeuStep3Planner $planner): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canEdit($flow, Auth::user()), 403);

    if ($flow->status === FlowStatus::Completed) {
        Flux::toast(variant: 'warning', text: 'Workflow ist bereits abgeschlossen.');

        return;
    }

    $step = $flow->currentStep();
    abort_unless($step !== null, 404);

    if ($step->key === 'it_benutzer') {
        $this->validate([
            'form.username' => ['required', 'string', 'min:3', 'max:64'],
        ], [
            'form.username.required' => 'Username ist erforderlich.',
        ]);

        if ($this->usernameAvailable === false) {
            $this->addError('form.username', 'Username ist belegt – bitte anderen wählen.');

            return;
        }

        if (! $this->usernameChecked) {
            $this->usernameAvailable = $planner->isUsernameAvailable((string) $this->form['username']);
            $this->usernameChecked = true;
            if ($this->usernameAvailable === false) {
                $this->addError('form.username', 'Username ist belegt – bitte anderen wählen.');

                return;
            }
        }

        $this->form['add_ldap_groups'] = $planner->mergeSelectedGroups(
            $this->shareGroupsSelected,
            $this->emailGroupsSelected,
            $this->d3GroupsSelected,
            $planner->forcedGroups($flow),
        );
    } else {
        [$rules, $messages] = StepFormRules::validation($step->inputs, $this->form);

        if ($rules !== []) {
            $this->validate($rules, $messages);
        }

        $this->form = StepFormRules::pruneHidden($step->inputs, $this->form);
    }

    $updated = $orchestrator->submitStep($flow, $this->form, (int) Auth::id());

    Flux::toast(
        variant: 'success',
        text: $updated->status === FlowStatus::Completed
            ? 'Workflow abgeschlossen.'
            : 'Schritt gespeichert – weiter zu Schritt '.$updated->current_step_position.'.',
    );

    $this->resetFormFromCurrentStep($planner);
};

$retryRun = function (int $runId, WorkflowOrchestrator $orchestrator): void {
    abort_unless(Auth::user()?->can('manage-app-workflows'), 403);

    $run = WorkflowActionRun::query()->findOrFail($runId);
    abort_unless((int) $run->flow_id === (int) $this->flowId, 404);

    $orchestrator->retryActionRun($run);
    Flux::toast(variant: 'success', text: 'Aktion erneut ausgeführt.');
};

$claim = function (FlowClaimService $claims): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canClaim($flow, Auth::user()), 403);
    $claims->claim($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Workflow dir zugewiesen.');
};

$release = function (FlowClaimService $claims): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canRelease($flow, Auth::user()), 403);
    $claims->release($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Zuweisung gelöscht – wieder Gruppen-Pool.');
};

$canClaim = computed(fn (): bool => FlowAccess::canClaim($this->flow, Auth::user()));

$canRelease = computed(fn (): bool => FlowAccess::canRelease($this->flow, Auth::user()));

$statusColor = function (ActionRunStatus $status): string {
    return match ($status) {
        ActionRunStatus::Succeeded, ActionRunStatus::Skipped => 'green',
        ActionRunStatus::Failed => 'red',
        ActionRunStatus::Partial, ActionRunStatus::Waiting => 'amber',
        ActionRunStatus::Running, ActionRunStatus::Queued => 'blue',
        default => 'zinc',
    };
};

?>

<div>
    <x-intranet-app-workflows::workflows-layout :heading="$this->pageTitle" subheading="Flow-Detail">
        <div class="space-y-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge>{{ $this->flow->type?->title }}</flux:badge>
                        <flux:badge :color="match($this->flow->status) {
                            FlowStatus::Active => 'blue',
                            FlowStatus::Completed => 'green',
                            default => 'zinc',
                        }">{{ $this->flow->status->value }}</flux:badge>
                        @if($this->flow->due_date)
                            <flux:badge color="zinc">Stichtag {{ $this->flow->due_date->format('d.m.Y') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="text-sm text-zinc-500">
                        Initiator: {{ $this->flow->initiator?->name ?? '—' }}
                        · Assignee: {{ FlowAccess::assigneeLabel($this->flow) }}
                    </flux:text>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($this->canClaim)
                        <flux:button variant="primary" wire:click="claim" icon="hand-raised">Mir zuweisen</flux:button>
                    @endif
                    @if($this->canRelease)
                        <flux:button variant="ghost" wire:click="release" icon="x-mark">Zuweisung löschen</flux:button>
                    @endif
                    <flux:button :href="route('apps.workflows.index')" wire:navigate variant="ghost" icon="arrow-left">Zur Liste</flux:button>
                </div>
            </div>

            @if($this->flow->error_summary)
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>Markierte Aktionsfehler</flux:callout.heading>
                    <flux:callout.text>
                        <pre class="whitespace-pre-wrap text-sm">{{ $this->flow->error_summary }}</pre>
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($this->flow->type->steps->sortBy('position') as $step)
                    @php
                        $done = $this->isCompleted || $step->position < $this->flow->current_step_position;
                        $current = ! $this->isCompleted && $step->position === $this->flow->current_step_position;
                    @endphp
                    <div @class([
                        'rounded-lg border p-3 text-zinc-50',
                        'border-emerald-400/50 bg-emerald-950/55' => $done,
                        'border-sky-400/60 bg-sky-950/60 ring-1 ring-sky-400/35' => $current,
                        'border-white/15 bg-black/20' => ! $done && ! $current,
                    ])>
                        <p @class([
                            'text-xs font-medium uppercase tracking-wide',
                            'text-emerald-200/80' => $done,
                            'text-sky-200/90' => $current,
                            'text-zinc-400' => ! $done && ! $current,
                        ])>Schritt {{ $step->position }}</p>
                        <p class="mt-0.5 text-sm font-semibold text-zinc-50">{{ $step->title }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @forelse($this->runsForStep($step->id) as $run)
                                <flux:badge size="sm" :color="$this->statusColor($run->status)" :title="$run->latest_message">
                                    {{ $run->stepAction?->action?->title ?? 'Aktion' }}
                                </flux:badge>
                            @empty
                                @if($done)
                                    <flux:badge size="sm" color="zinc">erledigt</flux:badge>
                                @elseif($current)
                                    <flux:badge size="sm" color="sky">offen</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">ausstehend</flux:badge>
                                @endif
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            @if($this->isCompleted)
                <flux:callout icon="check-circle" variant="success">
                    <flux:callout.heading>Workflow abgeschlossen</flux:callout.heading>
                    <flux:callout.text>Alle Schritte sind durchlaufen. Fachliche Side-Effects folgen in Phase B.</flux:callout.text>
                </flux:callout>
            @elseif($this->canEdit && $this->currentStep)
                <form wire:submit="submit" @class([
                    'mx-auto space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700',
                    'max-w-3xl' => $this->isItBenutzerStep,
                    'max-w-2xl' => ! $this->isItBenutzerStep,
                ])>
                    <flux:heading size="lg">Aktueller Schritt: {{ $this->currentStep->title }}</flux:heading>

                    @if($this->isItBenutzerStep)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-it-benutzer')
                    @else
                        <div class="space-y-4">
                            @foreach($this->currentStep->inputs as $input)
                                @continue(! StepFormRules::isVisible($input, $this->form))
                                @continue($input->typ === 'ldap_groups')
                                <x-intranet-app-workflows::step-field
                                    :input="$input"
                                    :live="in_array($input->key, $this->liveFieldKeys, true)"
                                />
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon="check">
                            Speichern &amp; weiter
                        </flux:button>
                    </div>
                </form>
            @else
                <flux:callout icon="lock-closed">
                    <flux:callout.heading>Kein Bearbeitungsrecht</flux:callout.heading>
                    <flux:callout.text>
                        @if(filled($this->flow->assignee_group_key) && $this->flow->assignee_user_id === null)
                            Dieser Schritt liegt bei der Gruppe {{ FlowAccess::assigneeLabel($this->flow) }}.
                            Bitte zuerst „Mir zuweisen“, wenn du Mitglied bist.
                        @else
                            Dieser Schritt ist {{ $this->flow->assignee?->name ? 'bei '.$this->flow->assignee->name : 'nicht dir zugewiesen' }}.
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="space-y-3">
                <flux:heading size="md">Action-Runs</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Pos</flux:table.column>
                        <flux:table.column>Schritt</flux:table.column>
                        <flux:table.column>Aktion</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Meldung</flux:table.column>
                        <flux:table.column>Attempts</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->flow->actionRuns->sortBy([
                            fn ($r) => $r->stepAction?->step?->position ?? 0,
                            fn ($r) => $r->position,
                        ]) as $run)
                            <flux:table.row wire:key="run-{{ $run->id }}">
                                <flux:table.cell>{{ $run->position }}</flux:table.cell>
                                <flux:table.cell>{{ $run->stepAction?->step?->position ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $run->stepAction?->action?->title }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$this->statusColor($run->status)">{{ $run->status->value }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-xs truncate">{{ $run->latest_message }}</flux:table.cell>
                                <flux:table.cell>{{ $run->attempts->count() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if(Auth::user()?->can('manage-app-workflows') && in_array($run->status, [ActionRunStatus::Failed, ActionRunStatus::Partial], true))
                                        <flux:button size="sm" variant="ghost" wire:click="retryRun({{ $run->id }})">Retry</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </x-intranet-app-workflows::workflows-layout>
</div>
