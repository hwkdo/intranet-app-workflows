<?php

declare(strict_types=1);

use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, title};

mount(function (): void {
    abort_unless(FlowAccess::canCreate(Auth::user()), 403);
});

title('Neuer Workflow');

$types = computed(function () {
    return WorkflowType::query()
        ->where('is_active', true)
        ->orderBy('title')
        ->get();
});

$typeIcon = function (string $key): string {
    return match ($key) {
        'ma_neu' => 'user-plus',
        'ma_umsetzung' => 'arrows-right-left',
        'ma_austritt' => 'user-minus',
        default => 'play',
    };
};

$typeButtonLabel = function (string $key): string {
    return match ($key) {
        'ma_neu' => 'Neueinstellung starten',
        'ma_umsetzung' => 'Umsetzung starten',
        'ma_austritt' => 'Austritt starten',
        default => 'Workflow starten',
    };
};

?>

<div>
    <x-intranet-app-workflows::workflows-layout heading="Neuer Workflow" subheading="Workflow-Typ auswählen">
        <div class="space-y-6">
            <flux:callout icon="information-circle">
                <flux:callout.heading>Welchen Prozess möchtest du starten?</flux:callout.heading>
                <flux:callout.text>
                    Wähle den passenden Workflow-Typ. Danach gibst du die Startangaben ein.
                </flux:callout.text>
            </flux:callout>

            @if($this->types->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Keine aktiven Workflow-Typen</flux:callout.heading>
                    <flux:callout.text>Es sind derzeit keine Workflow-Typen zum Starten freigeschaltet.</flux:callout.text>
                </flux:callout>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->types as $type)
                        <flux:card class="glass-card flex h-full flex-col justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                                    <flux:icon
                                        :name="$this->typeIcon($type->key)"
                                        class="size-8 text-zinc-600 dark:text-zinc-300"
                                    />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <flux:heading size="sm">{{ $type->title }}</flux:heading>
                                    @if(filled($type->description))
                                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                                            {{ $type->description }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>

                            <flux:button
                                variant="primary"
                                class="w-full"
                                :href="route('apps.workflows.flows.create', ['typeKey' => $type->key])"
                                wire:navigate
                                :icon="$this->typeIcon($type->key)"
                            >
                                {{ $this->typeButtonLabel($type->key) }}
                            </flux:button>
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>
    </x-intranet-app-workflows::workflows-layout>
</div>
