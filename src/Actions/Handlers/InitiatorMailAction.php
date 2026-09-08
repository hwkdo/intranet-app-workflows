<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Mail\InitiatorCompletedMail;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Hwkdo\IntranetAppWorkflows\Support\PhaseDGuard;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class InitiatorMailAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_neu.initiator_mail';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseDGuard::preflight()) {
            return $early;
        }

        $flow = $context->flow->loadMissing(['type', 'initiator']);
        $initiator = $flow->initiator;
        if (! $initiator) {
            return ActionResult::failed('Initiator fehlt am Flow', retryable: false);
        }

        $email = trim((string) ($initiator->email ?? ''));
        if ($email === '') {
            return ActionResult::failed('Initiator hat keine E-Mail-Adresse.', retryable: false);
        }

        $titel = FlowTitle::for($flow);
        $createdAt = $flow->created_at;
        $datum = $createdAt?->format('d.m.Y') ?? '–';
        $uhrzeit = $createdAt?->format('H:i') ?? '–';
        $inhalt = "Der von Ihnen am {$datum} um {$uhrzeit} Uhr initiierte Workflow {$titel} wurde soeben abgeschlossen.";
        $betreff = $titel.' | Abgeschlossen';

        if ($dry = PhaseDGuard::assertNotDryRunOrMessage(
            actionLabel: "Initiator-Mail an {$email}",
            output: ['initiator_email' => $email, 'subject' => $betreff],
        )) {
            return $dry;
        }

        try {
            Mail::to($email)->send(new InitiatorCompletedMail($betreff, $inhalt));
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Initiator-Mail fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Initiator-Mail an {$email} gesendet",
            output: [
                'initiator_email' => $email,
                'subject' => $betreff,
            ],
        );
    }
}
