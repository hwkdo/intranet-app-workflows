<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Services\FlowClaimService;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, title};

title('Workflows');

$flows = computed(function () {
    $user = Auth::user();

    return FlowAccess::constrainVisibleTo(
        WorkflowFlow::query()->with(['type', 'assignee', 'initiator']),
        $user,
    )
        ->latest()
        ->limit(50)
        ->get();
});

$canStart = computed(fn (): bool => Auth::check() && Auth::user()->can('see-app-workflows'));

$claim = function (int $flowId, FlowClaimService $claims): void {
    $flow = WorkflowFlow::query()->findOrFail($flowId);
    abort_unless(FlowAccess::canClaim($flow, Auth::user()), 403);

    $claims->claim($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Workflow dir zugewiesen.');
};

$release = function (int $flowId, FlowClaimService $claims): void {
    $flow = WorkflowFlow::query()->findOrFail($flowId);
    abort_unless(FlowAccess::canRelease($flow, Auth::user()), 403);

    $claims->release($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Zuweisung gelöscht – wieder Gruppen-Pool.');
};

?>

<div>
    <x-intranet-app-workflows::workflows-layout heading="Workflows" subheading="Übersicht">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">Offene und aktuelle Workflows</flux:heading>
                @if($this->canStart)
                    <flux:button variant="primary" :href="route('apps.workflows.flows.create')" wire:navigate icon="plus">
                        Neueinstellung starten
                    </flux:button>
                @endif
            </div>

            @if($this->flows->isEmpty())
                <flux:callout icon="information-circle">
                    <flux:callout.heading>Noch keine Workflows</flux:callout.heading>
                    <flux:callout.text>Starte eine Mitarbeiter-Neueinstellung, um den ersten Flow anzulegen.</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>ID</flux:table.column>
                        <flux:table.column>Typ</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Schritt</flux:table.column>
                        <flux:table.column>Mitarbeiter</flux:table.column>
                        <flux:table.column>Assignee</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->flows as $flow)
                            @php
                                $name = trim(($flow->getPayloadValue('vorname') ?? '').' '.($flow->getPayloadValue('nachname') ?? ''));
                            @endphp
                            <flux:table.row wire:key="flow-{{ $flow->id }}">
                                <flux:table.cell>{{ $flow->id }}</flux:table.cell>
                                <flux:table.cell>{{ $flow->type?->title }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="match($flow->status) {
                                        FlowStatus::Active => 'blue',
                                        FlowStatus::Completed => 'green',
                                        FlowStatus::Cancelled => 'zinc',
                                        default => 'amber',
                                    }">{{ $flow->status->value }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $flow->current_step_position }}</flux:table.cell>
                                <flux:table.cell>{{ $name !== '' ? $name : '—' }}</flux:table.cell>
                                <flux:table.cell>{{ FlowAccess::assigneeLabel($flow) }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        @if(FlowAccess::canClaim($flow, Auth::user()))
                                            <flux:button size="sm" variant="filled" wire:click="claim({{ $flow->id }})" icon="hand-raised">
                                                Mir zuweisen
                                            </flux:button>
                                        @endif
                                        @if(FlowAccess::canRelease($flow, Auth::user()))
                                            <flux:button size="sm" variant="ghost" wire:click="release({{ $flow->id }})" icon="x-mark">
                                                Zuweisung löschen
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" variant="ghost" :href="route('apps.workflows.flows.show', $flow)" wire:navigate>
                                            Öffnen
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </x-intranet-app-workflows::workflows-layout>
</div>
