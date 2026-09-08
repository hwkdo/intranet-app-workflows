{{-- Step IT-Rechte (ma_umsetzung): LDAP ±, Intranet-Rollen ±, Pickup --}}
<div class="space-y-6">
    @if(filled($this->flow->getPayloadValue('bemerkungen')))
        <flux:callout icon="chat-bubble-left-right">
            <flux:callout.heading>Bemerkungen aus Schritt 2</flux:callout.heading>
            <flux:callout.text>{{ $this->flow->getPayloadValue('bemerkungen') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:callout icon="user">
        <flux:callout.heading>Mitarbeiter</flux:callout.heading>
        <flux:callout.text>
            {{ $this->flow->getPayloadValue('vorname') }} {{ $this->flow->getPayloadValue('nachname') }}
            ({{ $this->flow->getPayloadValue('username') }})
        </flux:callout.text>
    </flux:callout>

    @if($ldapAnalogError)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Analog-Gruppen</flux:callout.heading>
            <flux:callout.text>{{ $ldapAnalogError }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:heading size="lg">Hinzufügen</flux:heading>

    <div class="space-y-4">
        <flux:heading size="md">AD-Gruppen Netzlaufwerke</flux:heading>
        @if($this->shareGroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine Share-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->shareGroupsAvailable as $group)
                    <flux:checkbox wire:model="shareGroupsSelected" value="{{ $group }}" :label="$group" wire:key="add-share-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">AD-Gruppen E-Mail Verteiler</flux:heading>
        @if($this->emailGroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine EV-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->emailGroupsAvailable as $group)
                    <flux:checkbox wire:model="emailGroupsSelected" value="{{ $group }}" :label="$group" wire:key="add-email-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">AD-Gruppen d3</flux:heading>
        @if($this->d3GroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine d3/RE-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->d3GroupsAvailable as $group)
                    <flux:checkbox wire:model="d3GroupsSelected" value="{{ $group }}" :label="$group" wire:key="add-d3-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <flux:heading size="md">Weitere (unveränderbare) AD-Gruppen</flux:heading>
        <flux:text class="text-sm text-zinc-500">Aus Standort, GVP, Defaults und Schritt-2-Optionen.</flux:text>
        @if($this->forcedLdapGroups === [])
            <flux:text class="text-sm">Keine Pflicht-Gruppen ermittelt.</flux:text>
        @else
            <ul class="list-inside list-disc space-y-1 text-sm">
                @foreach($this->forcedLdapGroups as $group)
                    <li wire:key="forced-{{ $group }}">{{ $group }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">Intranet-Rollen (vom Analog-User)</flux:heading>
        @if($this->intranetRolesAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine zusätzlichen Rollen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->intranetRolesAvailable as $role)
                    <flux:checkbox wire:model="intranetRolesSelected" value="{{ $role }}" :label="$role" wire:key="add-role-{{ $role }}" />
                @endforeach
            </div>
        @endif
    </div>

    <flux:separator />

    <flux:heading size="lg">Entfernen</flux:heading>

    @if($ldapCurrentError)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Aktuelle Gruppen</flux:callout.heading>
            <flux:callout.text>{{ $ldapCurrentError }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-4">
        <flux:heading size="md">Aktuelle AD-Gruppen (Share / EV / d3)</flux:heading>
        @php
            $removeCandidates = array_values(array_unique(array_merge(
                $this->removeShareGroupsAvailable,
                $this->removeEmailGroupsAvailable,
                $this->removeD3GroupsAvailable,
            )));
        @endphp
        @if($removeCandidates === [])
            <flux:text class="text-sm text-zinc-500">Keine entfernbaren Share/EV/d3-Gruppen.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($removeCandidates as $group)
                    <flux:checkbox wire:model="removeLdapGroupsSelected" value="{{ $group }}" :label="$group" wire:key="rm-ldap-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">Aktuelle Intranet-Rollen</flux:heading>
        @if($this->removeIntranetRolesAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine entfernbaren Intranet-Rollen.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->removeIntranetRolesAvailable as $role)
                    <flux:checkbox wire:model="removeIntranetRolesSelected" value="{{ $role }}" :label="$role" wire:key="rm-role-{{ $role }}" />
                @endforeach
            </div>
        @endif
    </div>

    <flux:separator />

    <flux:heading size="lg">Anrufübernahmegruppe</flux:heading>

    <flux:callout icon="phone">
        <flux:callout.heading>Aktuell beim Mitarbeiter</flux:callout.heading>
        <flux:callout.text>
            {{ $this->currentPickupName !== '' ? $this->currentPickupName : 'Keine Pickup-Gruppe gesetzt' }}
        </flux:callout.text>
    </flux:callout>

    @if((string) ($this->flow->getPayloadValue('anrufuebernahme_benoetigt') ?? '') === '1')
        @if($this->analogPickupName === '')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Referenz ohne Pickup</flux:callout.heading>
                <flux:callout.text>
                    Der gewählte Analog-User hat keine Anrufübernahmegruppe. Bitte „Nein“ wählen – die bestehende Pickup wird entfernt.
                </flux:callout.text>
            </flux:callout>
        @else
            <flux:callout icon="arrow-path">
                <flux:callout.heading>Pickup des Analog-Users</flux:callout.heading>
                <flux:callout.text>{{ $this->analogPickupName }}</flux:callout.text>
            </flux:callout>
        @endif

        <flux:field>
            <flux:label>Anrufübernahmegruppe übernehmen? *</flux:label>
            <flux:description>Nein entfernt die bestehende Pickup-Gruppe des Mitarbeiters.</flux:description>
            <flux:select wire:model="form.pickup_uebernehmen" placeholder="Bitte wählen…">
                <flux:select.option value="" disabled>Bitte wählen…</flux:select.option>
                <flux:select.option value="1" :disabled="$this->analogPickupName === ''">Ja – {{ $this->analogPickupName !== '' ? $this->analogPickupName : 'nicht verfügbar' }}</flux:select.option>
                <flux:select.option value="0">Nein (Pickup entfernen)</flux:select.option>
            </flux:select>
            <flux:error name="form.pickup_uebernehmen" />
        </flux:field>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Vorgesetzter: keine Anrufübernahme nötig</flux:callout.heading>
            <flux:callout.text>Am Stichtag wird eine ggf. vorhandene Pickup-Gruppe entfernt.</flux:callout.text>
        </flux:callout>
    @endif
</div>
