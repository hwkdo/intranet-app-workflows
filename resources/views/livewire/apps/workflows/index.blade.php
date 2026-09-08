<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Services\FlowClaimService;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Hwkdo\IntranetAppWorkflows\Support\FlowIndexQuery;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, state, title};

title('Workflows');

state([
    'search' => '',
    'showCompleted' => false,
    'showAll' => false,
]);

$canBrowseAll = computed(fn (): bool => FlowAccess::canBrowseAll(Auth::user()));

$flows = computed(function () {
    $query = WorkflowFlow::query()->with(['type', 'assignee', 'initiator']);

    return FlowIndexQuery::apply(
        query: $query,
        user: Auth::user(),
        showAll: (bool) $this->showAll,
        showCompleted: (bool) $this->showCompleted,
        search: (string) $this->search,
    )
        ->latest()
        ->limit(50)
        ->get();
});

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
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">
                        @if($this->showCompleted)
                            Workflows
                        @else
                            Offene und aktuelle Workflows
                        @endif
                    </flux:heading>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <div class="w-full max-w-md">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            label="Suche"
                            placeholder="Name, Typ, Username…"
                            icon="magnifying-glass"
                            clearable
                        />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <flux:switch
                            wire:model.live="showCompleted"
                            label="Abgeschlossene Workflows anzeigen"
                        />

                        @if($this->canBrowseAll)
                            <flux:switch
                                wire:model.live="showAll"
                                label="Zeige alle Workflows"
                                description="Admin-Sicht auf alle Flows"
                            />
                        @endif
                    </div>
                </div>
            </div>

            @if($this->flows->isEmpty())
                <flux:callout icon="information-circle">
                    <flux:callout.heading>Keine Workflows gefunden</flux:callout.heading>
                    <flux:callout.text>
                        @if(filled(trim($this->search)) || $this->showCompleted || $this->showAll)
                            Passe Suche oder Filter an.
                        @elseif(\Hwkdo\IntranetAppWorkflows\Support\FlowAccess::canCreate(Auth::user()))
                            Über „Neuer Workflow“ im Menü kannst du den ersten Flow anlegen.
                        @else
                            Sobald Workflows für dich sichtbar sind, erscheinen sie hier.
                        @endif
                    </flux:callout.text>
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
                                $name = FlowTitle::employeeName($flow);
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
