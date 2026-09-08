<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Mail\WorkflowTicketMail;
use Hwkdo\IntranetAppWorkflows\Support\MaUmsetzungTicketPlanner;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CreateTicketsMaUmsetzungAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BueRolesGatewayInterface $bue,
    ) {}

    public static function key(): string
    {
        return 'ma_umsetzung.create_tickets';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $planner = new MaUmsetzungTicketPlanner($this->bue);
        $tickets = $planner->plan($context->flow);
        $dump = $planner->payloadDump($context->flow);

        if ($tickets === []) {
            return ActionResult::succeeded('Keine Umsetzungstickets nötig');
        }

        $preview = array_map(
            fn (array $t): string => "{$t['key']} → {$t['recipient']}: {$t['subject']}",
            $tickets,
        );

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Umsetzungstickets ('.count($tickets).')',
            output: ['tickets' => $tickets],
            messages: $preview,
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];

        foreach ($tickets as $index => $ticket) {
            if ($ticket['recipient'] === '') {
                $errors[] = "{$ticket['key']}: Empfänger fehlt in Config";

                continue;
            }

            try {
                Mail::to($ticket['recipient'])
                    ->later(
                        now()->addSeconds(($index + 1) * 2),
                        new WorkflowTicketMail($ticket['subject'], $ticket['body'], $dump),
                    );
                $messages[] = "{$ticket['key']}-Mail an {$ticket['recipient']} geplant";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$ticket['key']}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Keine Umsetzungstickets konnten geplant werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Umsetzungstickets teilweise geplant',
                messages: $messages,
                errors: $errors,
                output: ['tickets_queued' => count($messages)],
            );
        }

        return ActionResult::succeeded(
            message: count($messages).' Umsetzungstickets geplant',
            output: ['tickets_queued' => count($messages)],
            messages: $messages,
        );
    }
}
