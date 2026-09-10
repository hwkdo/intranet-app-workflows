{{-- Step 4 (IT-Checkliste): Auto-Checks + manuelle Checks --}}
@php
    $status = $this->checklistStatus;
@endphp
<div class="space-y-6">
    @if(filled($this->flow->getPayloadValue('bemerkungen')))
        <flux:callout icon="chat-bubble-left-right">
            <flux:callout.heading>Bemerkungen aus Schritt 2</flux:callout.heading>
            <flux:callout.text>{{ $this->flow->getPayloadValue('bemerkungen') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="md">Automatische Prüfungen</flux:heading>
            <flux:button type="button" size="sm" variant="ghost" icon="arrow-path" wire:click="refreshChecklistStatus">
                Erneut prüfen
            </flux:button>
        </div>

        @if($status['ad_enabled'] === true)
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Active Directory</flux:callout.heading>
                <flux:callout.text>User {{ $status['username'] }} ist aktiviert.</flux:callout.text>
            </flux:callout>
        @elseif($status['ad_enabled'] === false)
            <flux:callout variant="danger" icon="x-circle">
                <flux:callout.heading>Active Directory</flux:callout.heading>
                <flux:callout.text>User {{ $status['username'] }} ist deaktiviert. Bitte aktivieren und erneut prüfen.</flux:callout.text>
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
                <flux:callout.text>Cloud-/Remote-Mailbox ist vorhanden (x500 / Exchange-Attribute).</flux:callout.text>
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Mailbox</flux:callout.heading>
                <flux:callout.text>Noch keine Mailbox-Indikatoren in LDAP gefunden.</flux:callout.text>
            </flux:callout>
        @endif

        @if($status['proxy_ok'])
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>Proxy-Adressen</flux:callout.heading>
                <flux:callout.text>Alle erwarteten Proxy-Adressen sind gesetzt.</flux:callout.text>
            </flux:callout>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Proxy-Adressen</flux:callout.heading>
                <flux:callout.text>
                    @if($status['missing_proxies'] !== [])
                        Es fehlen noch Adressen:
                    @else
                        Erwartete Proxy-Adressen konnten nicht ermittelt werden.
                    @endif
                </flux:callout.text>
            </flux:callout>
        @endif

        @if($status['actual_proxies'] !== [] || $status['expected_proxies'] !== [])
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm" class="mb-2">Aktuelle proxyAddresses</flux:heading>
                @if($status['actual_proxies'] === [])
                    <flux:text class="text-sm text-zinc-500">Keine</flux:text>
                @else
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach($status['actual_proxies'] as $proxy)
                            <li wire:key="proxy-actual-{{ md5($proxy) }}" @class(['text-amber-600 dark:text-amber-400' => in_array($proxy, $status['missing_proxies'], false)])>{{ $proxy }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($status['missing_proxies'] !== [])
                    <flux:heading size="sm" class="mb-2 mt-4">Fehlend (erwartet)</flux:heading>
                    <ul class="list-inside list-disc space-y-1 text-sm text-amber-700 dark:text-amber-300">
                        @foreach($status['missing_proxies'] as $proxy)
                            <li wire:key="proxy-missing-{{ md5($proxy) }}">{{ $proxy }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    @if($status['password'] !== '')
        <flux:callout variant="success" icon="key">
            <flux:callout.heading>Passwort</flux:callout.heading>
            <flux:callout.text>
                <span class="font-mono text-base tracking-wide">{{ $status['password'] }}</span>
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout variant="warning" icon="key">
            <flux:callout.heading>Passwort</flux:callout.heading>
            <flux:callout.text>Kein Passwort im Workflow-Payload (Activate-Schritt prüfen).</flux:callout.text>
        </flux:callout>
    @endif

    @if($status['bitwarden_sent'])
        <flux:callout variant="success" icon="paper-airplane">
            <flux:callout.heading>Bitwarden Send</flux:callout.heading>
            <flux:callout.text>
                Passwort wurde per Bitwarden Send an den Vorgesetzten geschickt
                @if($status['bitwarden_email'] !== '')
                    ({{ $status['bitwarden_email'] }})
                @endif.
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout icon="paper-airplane">
            <flux:callout.heading>Bitwarden Send</flux:callout.heading>
            <flux:callout.text>
                Beim Speichern dieses Schritts wird das Passwort per Bitwarden Send an den Vorgesetzten geschickt
                @if($status['bitwarden_email'] !== '')
                    ({{ $status['bitwarden_email'] }})
                @endif.
            </flux:callout.text>
        </flux:callout>
    @endif

    @if($status['bitwarden_invited'])
        <flux:callout variant="success" icon="lock-closed">
            <flux:callout.heading>Bitwarden Org-Invite</flux:callout.heading>
            <flux:callout.text>
                Einladung gesendet
                @if($status['bitwarden_invite_email'] !== '')
                    ({{ $status['bitwarden_invite_email'] }})
                @endif
                – Confirm erfolgt zentral in der Bitwarden-App.
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:callout icon="lock-closed">
            <flux:callout.heading>Bitwarden Org-Invite</flux:callout.heading>
            <flux:callout.text>
                Am Stichtag (Einsatz ab) wird der User in Bitwarden eingeladen; die Bestätigung läuft automatisch nach Registrierung.
            </flux:callout.text>
        </flux:callout>
    @endif

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

        @foreach($this->currentStep->inputs as $input)
            @continue(! \Hwkdo\IntranetAppWorkflows\Support\StepFormRules::isVisible($input, $this->checklistFormContext))
            <div wire:key="checklist-input-{{ $input->key }}">
                @if($input->key === 'telefon_check')
                    @php
                        $telefonNeinLabel = (string) (($input->config['nein_label'] ?? null) ?: 'Nein (Ticket)');
                    @endphp
                    <flux:field>
                        <flux:label>Telefonnummer {{ $status['phone_display'] }} eingerichtet? *</flux:label>
                        <flux:select wire:model="form.telefon_check" placeholder="Bitte wählen…">
                            <flux:select.option value="" disabled>Bitte wählen…</flux:select.option>
                            <flux:select.option value="1">Ja</flux:select.option>
                            <flux:select.option value="0">{{ $telefonNeinLabel }}</flux:select.option>
                        </flux:select>
                        <flux:error name="form.telefon_check" />
                    </flux:field>
                @else
                    <x-intranet-app-workflows::step-field :input="$input" />
                @endif
            </div>
        @endforeach
    </div>
</div>
