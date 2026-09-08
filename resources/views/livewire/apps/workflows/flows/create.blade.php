<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Hwkdo\IntranetAppWorkflows\Support\StepFormRules;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, state, title};

state([
    'typeKey' => 'ma_neu',
    'typeId' => null,
    /** @var array<string, mixed> */
    'form' => [],
]);

mount(function (?string $typeKey = null): void {
    abort_unless(FlowAccess::canCreate(Auth::user()), 403);

    $typeKey = filled($typeKey) ? $typeKey : 'ma_neu';
    abort_unless(in_array($typeKey, ['ma_neu', 'ma_umsetzung', 'ma_austritt'], true), 404);

    $type = WorkflowType::query()->where('key', $typeKey)->where('is_active', true)->firstOrFail();
    $this->typeKey = $typeKey;
    $this->typeId = $type->id;

    $step = $type->steps()->with('inputs')->orderBy('position')->firstOrFail();
    $this->form = StepFormRules::initialForm($step->inputs);
});

title(fn (): string => match ($this->typeKey) {
    'ma_umsetzung' => 'Umsetzung starten',
    'ma_austritt' => 'Austritt starten',
    default => 'Neueinstellung starten',
});

$type = computed(fn () => WorkflowType::query()->with(['steps.inputs'])->findOrFail($this->typeId));

$step = computed(fn () => $this->type->steps->sortBy('position')->first());

$liveFieldKeys = computed(fn (): array => StepFormRules::liveDependencyKeys($this->step->inputs));

$pageHeading = computed(fn (): string => match ($this->typeKey) {
    'ma_umsetzung' => 'Umsetzung',
    'ma_austritt' => 'Austritt',
    default => 'Neueinstellung',
});

$startCalloutText = computed(fn (): string => match ($this->typeKey) {
    'ma_austritt' => 'Nach dem Start geht der nächste Schritt an den Vorgesetzten des ausgewählten Mitarbeiters. Am Stichtag (Austrittsdatum + 1 Tag) laufen die IT-Automationen.',
    'ma_umsetzung' => 'Nach dem Start geht der nächste Schritt an den GVP-Vorgesetzten der gewählten Abteilung. Als Initiator kannst du den Status verfolgen, aber den Folge-Schritt nicht selbst bearbeiten.',
    default => 'Nach dem Start geht der nächste Schritt an den GVP-Vorgesetzten der gewählten Abteilung. Als Initiator kannst du den Status verfolgen, aber den Folge-Schritt nicht selbst bearbeiten.',
});

$start = function (WorkflowOrchestrator $orchestrator): void {
    $step = $this->step;

    [$rules, $messages] = StepFormRules::validation($step->inputs, $this->form);

    if ($rules !== []) {
        $this->validate($rules, $messages);
    }

    $this->form = StepFormRules::pruneHidden($step->inputs, $this->form);

    $flow = $orchestrator->startFlow(
        type: $this->type,
        initiatorId: (int) Auth::id(),
        formData: $this->form,
    );

    Flux::toast(variant: 'success', text: 'Workflow #'.$flow->id.' gestartet.');

    $this->redirect(route('apps.workflows.flows.show', $flow), navigate: true);
};

?>

<div>
    <x-intranet-app-workflows::workflows-layout :heading="$this->pageHeading" subheading="Schritt 1 – HR">
        <form wire:submit="start" class="mx-auto max-w-2xl space-y-6">
            <flux:callout icon="information-circle">
                <flux:callout.heading>Schritt 1 – HR</flux:callout.heading>
                <flux:callout.text>
                    {{ $this->startCalloutText }}
                </flux:callout.text>
            </flux:callout>

            <flux:heading size="lg">{{ $this->step->title }}</flux:heading>

            <div class="space-y-4">
                @foreach($this->step->inputs as $input)
                    @continue(! StepFormRules::isVisible($input, $this->form))
                    <div wire:key="create-input-{{ $input->key }}">
                        <x-intranet-app-workflows::step-field
                            :input="$input"
                            :live="in_array($input->key, $this->liveFieldKeys, true)"
                        />
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:button :href="route('apps.workflows.index')" wire:navigate variant="ghost">Abbrechen</flux:button>
                <flux:button type="submit" variant="primary" icon="play">Starten &amp; weiter</flux:button>
            </div>
        </form>
    </x-intranet-app-workflows::workflows-layout>
</div>
