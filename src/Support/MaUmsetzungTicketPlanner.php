<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Illuminate\Support\Collection;

/**
 * Checklisten-Tickets für ma_umsetzung (Legacy createTicketsMaUmsetzung).
 *
 * @phpstan-type TicketPlan array{key: string, subject: string, recipient: string, body: string}
 */
final class MaUmsetzungTicketPlanner
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

        $mitarbeiter = is_numeric($flow->getPayloadValue('mitarbeiter'))
            ? WorkflowModels::userQuery()->find((int) $flow->getPayloadValue('mitarbeiter'))
            : null;
        $hasCms = (bool) ($mitarbeiter?->cms_redaktion ?? false);
        $cmsNeeded = $this->isTruthy($flow->getPayloadValue('cms_benoetigt'));

        if ($cmsNeeded && ! $hasCms) {
            $tickets[] = $this->ticket(
                'wordpress_add',
                $prefix.'Wordpress Account benötigt',
                $this->recipient('it_verwaltung'),
                'Wordpress Account benötigt. Bitte mit Vorgesetzten Details klären.',
            );
        }

        if (! $cmsNeeded && $hasCms) {
            $tickets[] = $this->ticket(
                'wordpress_remove',
                $prefix.'Wordpress Account nicht mehr benötigt',
                $this->recipient('it_verwaltung'),
                'Wordpress Account nicht mehr benötigt.',
            );
        }

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
