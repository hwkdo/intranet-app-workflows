{{-- Step 3 (IT Benutzer): Username-Vorschlag + LDAP-Gruppen wie Legacy MaNeu/Step3 --}}
<div class="space-y-6">
    @if(filled($this->flow->getPayloadValue('bemerkungen')))
        <flux:callout icon="chat-bubble-left-right">
            <flux:callout.heading>Bemerkungen aus Schritt 2</flux:callout.heading>
            <flux:callout.text>{{ $this->flow->getPayloadValue('bemerkungen') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:field>
        <flux:label>Username (AD) *</flux:label>
        <flux:description>Vorschlag aus dem nächsten freien hwkdo-Konto; bitte prüfen.</flux:description>
        <div class="flex flex-wrap items-start gap-2">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.400ms="form.username" />
            </div>
            <flux:button type="button" wire:click="checkUsernameAvailability" variant="filled" icon="magnifying-glass">
                Prüfen
            </flux:button>
            <flux:button type="button" wire:click="suggestUsername" variant="ghost" icon="sparkles">
                Vorschlag
            </flux:button>
        </div>
        <flux:error name="form.username" />
        @if($usernameChecked)
            @if($usernameAvailable === true)
                <flux:badge color="green" size="sm" class="mt-2">Username ist frei</flux:badge>
            @elseif($usernameAvailable === false)
                <flux:badge color="red" size="sm" class="mt-2">Username ist belegt</flux:badge>
            @else
                <flux:badge color="amber" size="sm" class="mt-2">Prüfung nicht möglich (LDAP?)</flux:badge>
            @endif
        @endif
    </flux:field>

    @if($ldapAnalogError)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Analog-Gruppen</flux:callout.heading>
            <flux:callout.text>{{ $ldapAnalogError }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-4">
        <flux:heading size="md">Auswahl AD-Gruppen Netzlaufwerke</flux:heading>
        @if($this->shareGroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine Share-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->shareGroupsAvailable as $group)
                    <flux:checkbox wire:model="shareGroupsSelected" value="{{ $group }}" :label="$group" wire:key="share-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">Auswahl AD-Gruppen E-Mail Verteiler</flux:heading>
        @if($this->emailGroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine EV-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->emailGroupsAvailable as $group)
                    <flux:checkbox wire:model="emailGroupsSelected" value="{{ $group }}" :label="$group" wire:key="email-{{ $group }}" />
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">Auswahl AD-Gruppen d3</flux:heading>
        @if($this->d3GroupsAvailable === [])
            <flux:text class="text-sm text-zinc-500">Keine d3/RE-Gruppen vom Referenz-User.</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($this->d3GroupsAvailable as $group)
                    <flux:checkbox wire:model="d3GroupsSelected" value="{{ $group }}" :label="$group" wire:key="d3-{{ $group }}" />
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
            <ul class="list-inside list-disc space-y-1 text-sm text-zinc-200">
                @foreach($this->forcedLdapGroups as $group)
                    <li wire:key="forced-{{ $group }}">{{ $group }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
