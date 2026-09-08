{{-- Step IT (ma_austritt): Übersicht der Stichtag-Automationen, keine ma_neu-Onboarding-Checks --}}
@php
    $payload = $this->flow->payload ?? [];
    $mitarbeiterId = $payload['mitarbeiter'] ?? null;
    $mitarbeiter = is_numeric($mitarbeiterId)
        ? \Hwkdo\IntranetAppWorkflows\Support\WorkflowModels::userQuery()->find((int) $mitarbeiterId)
        : null;
    $austritt = $payload['austrittsdatum'] ?? null;
    $due = $this->flow->due_date;
    $weiterleitung = in_array($payload['email_weiterleitung_benoetigt'] ?? null, [1, '1', true], true);
    $forwardUserId = $payload['email_weiterleitung_an'] ?? null;
    $forwardUser = is_numeric($forwardUserId)
        ? \Hwkdo\IntranetAppWorkflows\Support\WorkflowModels::userQuery()->find((int) $forwardUserId)
        : null;

    $docs = is_array($payload['dokumente_assignments'] ?? null) ? $payload['dokumente_assignments'] : [];
    $aks = is_array($payload['arbeitskreise_assignments'] ?? null) ? $payload['arbeitskreise_assignments'] : [];
    $bws = is_array($payload['beauftragungen_assignments'] ?? null) ? $payload['beauftragungen_assignments'] : [];
    $assets = is_array($payload['assets_dispositions'] ?? null) ? $payload['assets_dispositions'] : [];

    $assetChoiceLabels = [
        'verbleibt_arbeitsplatz' => 'Verbleibt am Arbeitsplatz',
        'an_it' => 'An IT übergeben',
        'werde_ich_erhalten' => 'VG erhält (geplant)',
        'habe_ich_erhalten' => 'VG hat erhalten (sofort)',
        'von_anderem' => 'Anderer Mitarbeiter',
        'vermisst' => 'Vermisst',
    ];
@endphp

<div class="space-y-6">
    <flux:callout icon="information-circle">
        <flux:callout.heading>IT – Freigabe zum Stichtag</flux:callout.heading>
        <flux:callout.text>
            Mit „Speichern &amp; weiter“ werden die Offboarding-Aktionen für den Stichtag eingeplant
            (Austrittsdatum + 1 Tag). Es gibt keine Onboarding-Checkliste wie bei Neueinstellung.
        </flux:callout.text>
    </flux:callout>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-2">
        <flux:heading size="sm">Mitarbeiter</flux:heading>
        <flux:text>
            {{ $mitarbeiter?->name ?? ('#'.$mitarbeiterId) }}
            @if(filled($mitarbeiter?->username))
                <span class="text-zinc-500">({{ $mitarbeiter->username }})</span>
            @endif
        </flux:text>
        <flux:text class="text-sm text-zinc-500">
            Austrittsdatum: {{ $austritt ? \Illuminate\Support\Carbon::parse((string) $austritt)->format('d.m.Y') : '—' }}
            · Stichtag Automationen: {{ $due ? $due->format('d.m.Y') : '—' }}
        </flux:text>
    </div>

    @if(filled($payload['bemerkungen'] ?? null) || filled($payload['bemerkungen_vg'] ?? null))
        <flux:callout icon="chat-bubble-left-right">
            <flux:callout.heading>Bemerkungen</flux:callout.heading>
            @if(filled($payload['bemerkungen'] ?? null))
                <flux:callout.text><strong>HR:</strong> {{ $payload['bemerkungen'] }}</flux:callout.text>
            @endif
            @if(filled($payload['bemerkungen_vg'] ?? null))
                <flux:callout.text><strong>Vorgesetzter:</strong> {{ $payload['bemerkungen_vg'] }}</flux:callout.text>
            @endif
        </flux:callout>
    @endif

    <div class="space-y-3">
        <flux:heading size="md">Geplante Automationen am Stichtag</flux:heading>
        <ol class="list-decimal list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
            <li>Intranet-User deaktivieren (<code>active=false</code>)</li>
            <li>Mailbox → Shared (LDAP-Lizenzgruppen noch vorhanden)</li>
            <li>
                E-Mail-Weiterleitung
                @if($weiterleitung)
                    an {{ $forwardUser?->name ?? ('User #'.$forwardUserId) }}
                @else
                    <span class="text-zinc-500">— nicht gewünscht</span>
                @endif
            </li>
            <li>Dokumente / Arbeitskreise / Beauftragungen / Assets übertragen</li>
            <li>LDAP-Gruppen entfernen, OU Austritt, Telefon/Fax leeren, AD deaktivieren</li>
            <li>Bitwarden Offboard, Cisco Offboard, Pickup entfernen</li>
            <li>Mail an Initiator</li>
        </ol>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-1">
            <flux:heading size="sm">Dokumente</flux:heading>
            <flux:text class="text-sm">{{ count($docs) }} Zuweisung(en)</flux:text>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-1">
            <flux:heading size="sm">Arbeitskreise</flux:heading>
            <flux:text class="text-sm">{{ count($aks) }} Eintrag/Einträge</flux:text>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-1">
            <flux:heading size="sm">Beauftragungen</flux:heading>
            <flux:text class="text-sm">{{ count($bws) }} Eintrag/Einträge</flux:text>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 space-y-1">
            <flux:heading size="sm">Assets</flux:heading>
            <flux:text class="text-sm">{{ count($assets) }} Disposition(en)</flux:text>
        </div>
    </div>

    @if($assets !== [])
        <div class="space-y-2">
            <flux:heading size="sm">Asset-Dispositionen</flux:heading>
            <ul class="space-y-1 text-sm">
                @foreach($assets as $assetId => $row)
                    @php
                        $choice = (string) ($row['choice'] ?? '');
                        $label = $assetChoiceLabels[$choice] ?? ($choice !== '' ? $choice : '—');
                    @endphp
                    <li wire:key="austritt-asset-sum-{{ $assetId }}">
                        Asset #{{ $assetId }}: {{ $label }}
                        @if($choice === 'habe_ich_erhalten')
                            <span class="text-zinc-500">(bereits bei Step 2 ausgeführt)</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4">
        @foreach($this->currentStep->inputs as $input)
            @continue(! \Hwkdo\IntranetAppWorkflows\Support\StepFormRules::isVisible($input, $this->form))
            <div wire:key="austritt-it-input-{{ $input->key }}">
                <x-intranet-app-workflows::step-field
                    :input="$input"
                    :live="in_array($input->key, $this->liveFieldKeys, true)"
                />
            </div>
        @endforeach
    </div>
</div>
