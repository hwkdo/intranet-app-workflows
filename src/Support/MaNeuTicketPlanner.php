<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Illuminate\Support\Collection;

/**
 * Baut die Ticket-Mails für ma_neu (Legacy createTicketsMaNeu).
 *
 * @phpstan-type TicketPlan array{key: string, subject: string, recipient: string, body: string}
 */
final class MaNeuTicketPlanner
{
    public function __construct(
        private readonly ?BueRolesGatewayInterface $bue = null,
    ) {}

    /**
     * @return list<TicketPlan>
     */
    public function plan(WorkflowFlow $flow): array
    {
        $prefix = FlowTitle::for($flow).' | ';
        $tickets = [];

        if ($this->isFalsy($flow->getPayloadValue('hardware_benoetigt_check'))) {
            $hardware = (string) $flow->getPayloadValue('hardware', '');
            if ($hardware === '1') {
                $tickets[] = $this->ticket(
                    'hardware',
                    $prefix.'Neue Hardware Benötigt',
                    $this->recipient('it_verwaltung'),
                    (string) $flow->getPayloadValue('hardware_neu_benoetigt', ''),
                );
            } elseif ($hardware === '2') {
                $tickets[] = $this->ticket(
                    'hardware',
                    $prefix.'Hardware wird übernommen',
                    $this->recipient('it_verwaltung'),
                    $this->hardwareTakeoverBody($flow),
                );
            }
        }

        if ($this->isTruthy($flow->getPayloadValue('bue_rechte_benoetigt'))
            && $this->isFalsy($flow->getPayloadValue('bue_check'))) {
            $tickets[] = $this->ticket(
                'bue',
                $prefix.'BuE Account benötigt',
                $this->recipient('it_verwaltung'),
                $this->bueBody($flow),
            );
        }

        if ($this->isTruthy($flow->getPayloadValue('farbdruck_benoetigt'))
            && $this->isFalsy($flow->getPayloadValue('farbdruck_check'))) {
            $tickets[] = $this->ticket(
                'farbdruck',
                $prefix.'Farbdruck benötigt',
                $this->recipient('it_verwaltung'),
                'Farbdruck benötigt',
            );
        }

        if ($this->isFalsy($flow->getPayloadValue('yubikey_check'))) {
            $tickets[] = $this->ticket(
                'yubikey',
                $prefix.'YubiKey einrichten',
                $this->recipient('it_verwaltung'),
                'YubiKey ist noch nicht eingerichtet.',
            );
        }

        if ($this->isFalsy($flow->getPayloadValue('telefon_check'))) {
            $phone = app(MaNeuChecklistInspector::class)->phoneDisplay($flow);
            $tickets[] = $this->ticket(
                'telefon',
                $prefix.'Telefonnummer einrichten',
                $this->recipient('it_verwaltung'),
                'Telefonnummer '.$phone.' ist noch nicht eingerichtet.',
            );
        }

        if ($this->isTruthy($flow->getPayloadValue('istausbilder'))) {
            $tickets[] = $this->ticket(
                'schulung',
                $prefix.'Account Schulung',
                $this->recipient('it_schulung'),
                'Bitte DomänenAccount Schulung erstellen',
            );
        }

        if ((bool) config('intranet-app-workflows.phase_c.ticket_perseus_bei_ma_neu', true)) {
            $tickets[] = $this->ticket(
                'perseus',
                $prefix.'Perseus Konto',
                $this->recipient('it_verwaltung'),
                'Bitte Perseus Konto erstellen',
            );
        }

        if ($this->isTruthy($flow->getPayloadValue('cms_benoetigt'))) {
            $tickets[] = $this->ticket(
                'wordpress',
                $prefix.'Wordpress Account benötigt',
                $this->recipient('it_verwaltung'),
                'Wordpress Account benötigt. Bitte mit Vorgesetzten Details klären.',
            );
        }

        $tickets[] = $this->ticket(
            'dms',
            $prefix.'d3 Account benötigt',
            $this->recipient('dms'),
            'd3 Account benötigt',
        );

        $username = (string) $flow->getPayloadValue('username', '');
        $tickets[] = $this->ticket(
            'perso',
            $prefix.'Neuer Mitarbeiter hat hwkdo-Nummer '.$username,
            $this->recipient('perso'),
            'Neuer Mitarbeiter',
        );

        $tickets[] = $this->ticket(
            'hausmeister',
            $prefix.'Neuer Mitarbeiter',
            $this->recipient('haustechnik'),
            'Neuer Mitarbeiter',
        );

        $tickets[] = $this->ticket(
            'arbeitsschutz',
            $prefix.'Neuer Mitarbeiter',
            $this->recipient('arbeitsschutz'),
            'Neuer Mitarbeiter',
        );

        return $tickets;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadDump(WorkflowFlow $flow): array
    {
        $payload = $flow->payload ?? [];
        $dump = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $dump[(string) $key] = implode(', ', array_map(strval(...), $value));
            } else {
                $dump[(string) $key] = $value;
            }
        }

        return $dump;
    }

    /**
     * @return TicketPlan
     */
    private function ticket(string $key, string $subject, string $recipient, string $body): array
    {
        return [
            'key' => $key,
            'subject' => $subject,
            'recipient' => $recipient,
            'body' => $body,
        ];
    }

    private function recipient(string $key): string
    {
        $recipients = config('intranet-app-workflows.phase_c.ticket_recipients', []);

        return (string) ($recipients[$key] ?? '');
    }

    private function hardwareTakeoverBody(WorkflowFlow $flow): string
    {
        $fromId = $flow->getPayloadValue('hardware_uebernehmen_von');
        $fromUser = $fromId ? WorkflowModels::userQuery()->find($fromId) : null;
        $label = $fromUser
            ? trim(($fromUser->vorname ?? '').' '.($fromUser->nachname ?? ''))
            : (string) $fromId;

        $body = 'Hardware wird uebernommen von: '.$label;

        if ($fromUser && class_exists(\Hwkdo\IntranetAppAssets\Models\Asset::class)) {
            /** @var Collection<int, \Hwkdo\IntranetAppAssets\Models\Asset> $assets */
            $assets = \Hwkdo\IntranetAppAssets\Models\Asset::query()
                ->where('user_id', $fromUser->id)
                ->get(['id', 'name']);

            if ($assets->isNotEmpty()) {
                $body .= '<br/>Eingetragene Assets '.$label.' : <br/>';
                foreach ($assets as $asset) {
                    $name = e((string) $asset->name);
                    if (\Illuminate\Support\Facades\Route::has('apps.assets.show')) {
                        $url = e(route('apps.assets.show', $asset));
                        $body .= '<a href="'.$url.'" target="_blank">'.$name.'</a> <br/>';
                    } else {
                        $body .= $name.' <br/>';
                    }
                }
            }
        }

        return $body;
    }

    private function bueBody(WorkflowFlow $flow): string
    {
        $analogId = $flow->getPayloadValue('bue_rechte_analog_zu');
        $analog = $analogId ? WorkflowModels::userQuery()->find($analogId) : null;
        $label = $analog
            ? trim(($analog->vorname ?? '').' '.($analog->nachname ?? ''))
            : (string) $analogId;

        $body = '<p>BuE Rechte Analog zu: '.$label.'</p>';

        $bueUsername = $analog?->bue_username ?? null;
        if ($bueUsername && $this->bue) {
            $roles = $this->bue->getRoles((string) $bueUsername);
            if ($roles->isNotEmpty()) {
                $body .= '<br/>';
                foreach ($roles as $role) {
                    $body .= e((string) $role).'<br/>';
                }
            }
        }

        return $body;
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function isFalsy(mixed $value): bool
    {
        return in_array($value, [false, 0, '0', null, ''], true);
    }
}
