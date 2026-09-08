{{-- Step IT-Checkliste (ma_umsetzung) --}}
@php
    $status = $this->umsetzungChecklistStatus;
@endphp
<div class="space-y-6">
    @if(filled($this->flow->getPayloadValue('bemerkungen')))
        <flux:callout icon="chat-bubble-left-right">
            <flux:callout.heading>Bemerkungen aus Schritt 2</flux:callout.heading>
            <flux:callout.text>{{ $this->flow->getPayloadValue('bemerkungen') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-3">
        <flux:heading size="md">Automatische Prüfungen</flux:heading>

        @if($status['ad_enabled'] === true)
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Active Directory</flux:callout.heading>
                <flux:callout.text>User {{ $status['username'] }} ist aktiviert.</flux:callout.text>
            </flux:callout>
        @elseif($status['ad_enabled'] === false)
            <flux:callout variant="danger" icon="x-circle">
                <flux:callout.heading>Active Directory</flux:callout.heading>
                <flux:callout.text>User {{ $status['username'] }} ist deaktiviert.</flux:callout.text>
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Active Directory</flux:callout.heading>
                <flux:callout.text>User {{ $status['username'] ?: '(kein Username)' }} konnte nicht geprüft werden.</flux:callout.text>
            </flux:callout>
        @endif

        @if($status['has_mailbox'])
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Mailbox</flux:callout.heading>
                <flux:callout.text>Mailbox-Indikatoren in LDAP vorhanden.</flux:callout.text>
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Mailbox</flux:callout.heading>
                <flux:callout.text>Keine Mailbox-Indikatoren gefunden.</flux:callout.text>
            </flux:callout>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="md">Manuelle Checks</flux:heading>

        @if((string) ($this->flow->getPayloadValue('hardware') ?? '') === '3')
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Hardware</flux:callout.heading>
                <flux:callout.text>Keine Hardware nötig – bereits vorhanden.</flux:callout.text>
            </flux:callout>
        @endif

        @if((string) ($this->flow->getPayloadValue('bue_rechte_benoetigt') ?? '') === '0')
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>BuE</flux:callout.heading>
                <flux:callout.text>Keine BuE-Rechte benötigt.</flux:callout.text>
            </flux:callout>
        @endif

        @if((string) ($this->flow->getPayloadValue('farbdruck_benoetigt') ?? '') === '0')
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Farbdruck</flux:callout.heading>
                <flux:callout.text>Farbdruck wird nicht benötigt.</flux:callout.text>
            </flux:callout>
        @endif

        @if((string) ($this->flow->getPayloadValue('cms_benoetigt') ?? '') === '0')
            <flux:callout icon="document">
                <flux:callout.heading>CMS</flux:callout.heading>
                <flux:callout.text>CMS nicht benötigt – ggf. Entzug-Ticket nach Abschluss.</flux:callout.text>
            </flux:callout>
        @endif

        @foreach($this->currentStep->inputs as $input)
            @continue(! \Hwkdo\IntranetAppWorkflows\Support\StepFormRules::isVisible($input, $this->checklistFormContext))
            <div wire:key="umsetzung-check-{{ $input->key }}">
                <x-intranet-app-workflows::step-field :input="$input" />
            </div>
        @endforeach
    </div>
</div>
