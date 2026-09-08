<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Mail\WorkflowTicketMail;
use Hwkdo\IntranetAppWorkflows\Support\MaUmsetzungSupportTicketPlanner;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CreateTicketsUnterstuetzungAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_umsetzung.create_tickets_unterstuetzung';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $planner = new MaUmsetzungSupportTicketPlanner;
        $tickets = $planner->plan($context->flow);

        if ($tickets === []) {
            return ActionResult::succeeded('Keine Unterstützungstickets nötig');
        }

        $preview = array_map(
            fn (array $t): string => "{$t['key']} → {$t['recipient']}: {$t['subject']}",
            $tickets,
        );

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Unterstützungstickets ('.count($tickets).')',
            output: ['tickets' => $tickets],
            messages: $preview,
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];
        $dump = $context->payload;

        foreach ($tickets as $index => $ticket) {
            if ($ticket['recipient'] === '') {
                $errors[] = "{$ticket['key']}: Empfänger fehlt in Config";

                continue;
            }

            try {
                Mail::to($ticket['recipient'])
                    ->later(
                        now()->addSeconds(($index + 1) * 2),
                        new WorkflowTicketMail($ticket['subject'], $ticket['body'], is_array($dump) ? $dump : []),
                    );
                $messages[] = "{$ticket['key']}-Mail an {$ticket['recipient']} geplant";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$ticket['key']}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Keine Unterstützungstickets konnten geplant werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Unterstützungstickets teilweise geplant',
                messages: $messages,
                errors: $errors,
                output: ['tickets_queued' => count($messages)],
            );
        }

        return ActionResult::succeeded(
            message: count($messages).' Unterstützungstickets geplant',
            output: ['tickets_queued' => count($messages)],
            messages: $messages,
        );
    }
}
