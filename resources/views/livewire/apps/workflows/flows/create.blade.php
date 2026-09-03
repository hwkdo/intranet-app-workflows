<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Hwkdo\IntranetAppWorkflows\Support\StepFormRules;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, state, title};

title('Neueinstellung starten');

state([
    'typeId' => null,
    /** @var array<string, mixed> */
    'form' => [],
]);

mount(function (): void {
    $type = WorkflowType::query()->where('key', 'ma_neu')->where('is_active', true)->firstOrFail();
    $this->typeId = $type->id;

    $step = $type->steps()->with('inputs')->orderBy('position')->firstOrFail();
    $this->form = StepFormRules::initialForm($step->inputs);
});

$type = computed(fn () => WorkflowType::query()->with(['steps.inputs'])->findOrFail($this->typeId));

$step = computed(fn () => $this->type->steps->sortBy('position')->first());

$liveFieldKeys = computed(fn (): array => StepFormRules::liveDependencyKeys($this->step->inputs));

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
    <x-intranet-app-workflows::workflows-layout heading="Neueinstellung" subheading="Schritt 1 – HR">
        <form wire:submit="start" class="mx-auto max-w-2xl space-y-6">
            <flux:callout icon="information-circle">
                <flux:callout.heading>Phase A</flux:callout.heading>
                <flux:callout.text>
                    Fachliche Aktionen (AD, Tickets, …) sind noch Platzhalter. Du kannst den Flow aber end-to-end durchklicken;
                    der Assignee fällt auf dich als Initiator zurück.
                </flux:callout.text>
            </flux:callout>

            <flux:heading size="lg">{{ $this->step->title }}</flux:heading>

            <div class="space-y-4">
                @foreach($this->step->inputs as $input)
                    @continue(! StepFormRules::isVisible($input, $this->form))
                    <x-intranet-app-workflows::step-field
                        :input="$input"
                        :live="in_array($input->key, $this->liveFieldKeys, true)"
                    />
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:button :href="route('apps.workflows.index')" wire:navigate variant="ghost">Abbrechen</flux:button>
                <flux:button type="submit" variant="primary" icon="play">Starten &amp; weiter</flux:button>
            </div>
        </form>
    </x-intranet-app-workflows::workflows-layout>
</div>
