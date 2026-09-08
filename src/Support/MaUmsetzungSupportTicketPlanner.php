<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;

/**
 * Unterstützungstickets nach Step 2 (Legacy createTicketsUnterstuetzung).
 *
 * @phpstan-type TicketPlan array{key: string, subject: string, recipient: string, body: string}
 */
final class MaUmsetzungSupportTicketPlanner
{
    /**
     * @return list<TicketPlan>
     */
    public function plan(WorkflowFlow $flow): array
    {
        $prefix = FlowTitle::for($flow).' | ';
        $tickets = [];

        if ($this->isTruthy($flow->getPayloadValue('unterstuetzung_it_benoetigt'))) {
            $tickets[] = [
                'key' => 'support_it',
                'subject' => $prefix.'Unterstützung IT',
                'recipient' => $this->recipient('it_verwaltung'),
                'body' => (string) $flow->getPayloadValue('unterstuetzung_it_text', ''),
            ];
        }

        if ($this->isTruthy($flow->getPayloadValue('unterstuetzung_hausmeister_benoetigt'))) {
            $tickets[] = [
                'key' => 'support_hausmeister',
                'subject' => $prefix.'Unterstützung Hausmeister',
                'recipient' => $this->recipient('haustechnik'),
                'body' => (string) $flow->getPayloadValue('unterstuetzung_hausmeister_text', ''),
            ];
        }

        return $tickets;
    }

    private function recipient(string $key): string
    {
        $recipients = config('intranet-app-workflows.phase_c.ticket_recipients', []);

        return (string) ($recipients[$key] ?? '');
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
