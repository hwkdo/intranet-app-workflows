{{-- ma_austritt Step 2: Weiterleitung + Inventar-Zuweisungen + Asset-Dispositionen --}}
@php
    $inventory = $this->austrittInventory;
    $activeUsers = $this->austrittActiveUsers;
    $standorte = $this->austrittStandorte;
@endphp

<div class="space-y-6" data-tour="austritt-vorgesetzter">
    <div class="space-y-4">
        @foreach($this->currentStep->inputs as $input)
            @continue(! \Hwkdo\IntranetAppWorkflows\Support\StepFormRules::isVisible($input, $this->form))
            <div wire:key="austritt-input-{{ $input->key }}">
                <x-intranet-app-workflows::step-field
                    :input="$input"
                    :live="in_array($input->key, $this->liveFieldKeys, true)"
                />
            </div>
        @endforeach
    </div>

    {{-- Dokumente --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="sm">Dokumente</flux:heading>
            @if(count($inventory['dokumente'] ?? []) > 0)
                <div class="flex items-end gap-2">
                    <flux:select wire:model="austrittBulkDokumenteUserId" variant="listbox" searchable clearable placeholder="Allen zuweisen…" class="min-w-56">
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="button" size="sm" wire:click="austrittBulkAssignDokumente">Alle zuweisen</flux:button>
                </div>
            @endif
        </div>
        @forelse($inventory['dokumente'] ?? [] as $row)
            <div class="grid gap-2 sm:grid-cols-[1fr_16rem] items-center border-b border-zinc-200 dark:border-zinc-700 pb-2" wire:key="doc-{{ $row['key'] }}">
                <flux:text>{{ $row['label'] }}</flux:text>
                <flux:select
                    wire:model="form.dokumente_assignments.{{ $row['key'] }}.to_user_id"
                    variant="listbox"
                    searchable
                    placeholder="Nachfolger…"
                >
                    @foreach($activeUsers as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500">Keine Dokumente betroffen.</flux:text>
        @endforelse
    </div>

    {{-- Arbeitskreise --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="sm">Arbeitskreise</flux:heading>
            @if(count($inventory['arbeitskreise'] ?? []) > 0)
                <div class="flex items-end gap-2">
                    <flux:select wire:model="austrittBulkAkUserId" variant="listbox" searchable clearable placeholder="Allen zuweisen…" class="min-w-56">
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="button" size="sm" wire:click="austrittBulkAssignArbeitskreise">Alle zuweisen</flux:button>
                </div>
            @endif
        </div>
        @forelse($inventory['arbeitskreise'] ?? [] as $row)
            <div class="grid gap-2 sm:grid-cols-[1fr_12rem_16rem] items-center border-b border-zinc-200 dark:border-zinc-700 pb-2" wire:key="ak-{{ $row['key'] }}">
                <flux:text>{{ $row['label'] }}</flux:text>
                <flux:select wire:model.live="form.arbeitskreise_assignments.{{ $row['key'] }}.action" placeholder="Aktion…">
                    <flux:select.option value="transfer">Übertragen</flux:select.option>
                    <flux:select.option value="remove">Entfernen</flux:select.option>
                </flux:select>
                @if(($this->form['arbeitskreise_assignments'][$row['key']]['action'] ?? 'transfer') !== 'remove')
                    <flux:select
                        wire:model="form.arbeitskreise_assignments.{{ $row['key'] }}.to_user_id"
                        variant="listbox"
                        searchable
                        placeholder="Nachfolger…"
                    >
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <div></div>
                @endif
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500">Keine Arbeitskreis-Mitgliedschaften.</flux:text>
        @endforelse
    </div>

    {{-- Beauftragungen --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="sm">Beauftragungen</flux:heading>
            @if(count($inventory['beauftragungen'] ?? []) > 0)
                <div class="flex items-end gap-2">
                    <flux:select wire:model="austrittBulkBwUserId" variant="listbox" searchable clearable placeholder="Allen zuweisen…" class="min-w-56">
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="button" size="sm" wire:click="austrittBulkAssignBeauftragungen">Alle zuweisen</flux:button>
                </div>
            @endif
        </div>
        @forelse($inventory['beauftragungen'] ?? [] as $row)
            <div class="grid gap-2 sm:grid-cols-[1fr_12rem_16rem] items-center border-b border-zinc-200 dark:border-zinc-700 pb-2" wire:key="bw-{{ $row['key'] }}">
                <flux:text>{{ $row['label'] }}</flux:text>
                <flux:select wire:model.live="form.beauftragungen_assignments.{{ $row['key'] }}.action" placeholder="Aktion…">
                    <flux:select.option value="transfer">Übertragen</flux:select.option>
                    <flux:select.option value="remove">Entfernen</flux:select.option>
                </flux:select>
                @if(($this->form['beauftragungen_assignments'][$row['key']]['action'] ?? 'transfer') !== 'remove')
                    <flux:select
                        wire:model="form.beauftragungen_assignments.{{ $row['key'] }}.to_user_id"
                        variant="listbox"
                        searchable
                        placeholder="Nachfolger…"
                    >
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <div></div>
                @endif
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500">Keine Beauftragungen.</flux:text>
        @endforelse
    </div>

    {{-- Assets --}}
    <div class="space-y-3">
        <flux:heading size="sm">Assets</flux:heading>
        @forelse($inventory['assets'] ?? [] as $asset)
            @php $aid = $asset['id']; @endphp
            <div class="space-y-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3" wire:key="asset-{{ $aid }}">
                <flux:text class="font-medium">{{ $asset['label'] }}</flux:text>
                <flux:select wire:model.live="form.assets_dispositions.{{ $aid }}.choice" placeholder="Auswahl…">
                    <flux:select.option value="verbleibt_arbeitsplatz">Verbleibt am Arbeitsplatz</flux:select.option>
                    <flux:select.option value="an_it">Wird an die IT übergeben</flux:select.option>
                    <flux:select.option value="werde_ich_erhalten">Werde ich erhalten</flux:select.option>
                    <flux:select.option value="habe_ich_erhalten">Habe ich erhalten</flux:select.option>
                    <flux:select.option value="von_anderem">Von anderem Mitarbeiter übernommen</flux:select.option>
                    <flux:select.option value="vermisst">Vermisst</flux:select.option>
                </flux:select>

                @php $choice = $this->form['assets_dispositions'][$aid]['choice'] ?? ''; @endphp

                @if($choice === 'verbleibt_arbeitsplatz')
                    <flux:select wire:model="form.assets_dispositions.{{ $aid }}.standort_id" variant="listbox" searchable placeholder="Standort…">
                        @foreach($standorte as $s)
                            <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif(in_array($choice, ['an_it', 'werde_ich_erhalten'], true))
                    <flux:input type="datetime-local" wire:model="form.assets_dispositions.{{ $aid }}.datetime" label="Datum / Uhrzeit" />
                @elseif($choice === 'von_anderem')
                    <flux:select wire:model="form.assets_dispositions.{{ $aid }}.to_user_id" variant="listbox" searchable placeholder="Mitarbeiter…">
                        @foreach($activeUsers as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
        @empty
            <flux:text class="text-sm text-zinc-500">Keine Assets zugewiesen.</flux:text>
        @endforelse
    </div>
</div>
