<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Mail\WorkflowTicketMail;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Hwkdo\IntranetAppWorkflows\Support\PhaseDGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Step 1: Vorab-Info an austretenden Mitarbeiter und dessen Vorgesetzten.
 */
final class VorabMailAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.vorab_mail';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseDGuard::preflight()) {
            return $early;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $mitarbeiter = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $mitarbeiter) {
            return ActionResult::failed('Mitarbeiter nicht gefunden', retryable: false);
        }

        $recipients = [];
        $maEmail = trim((string) ($mitarbeiter->email ?? ''));
        if ($maEmail !== '') {
            $recipients[] = $maEmail;
        }

        $vorgesetzter = method_exists($mitarbeiter, 'vorgesetzter') ? $mitarbeiter->vorgesetzter() : null;
        $vgEmail = trim((string) ($vorgesetzter?->email ?? ''));
        if ($vgEmail !== '' && ! in_array($vgEmail, $recipients, true)) {
            $recipients[] = $vgEmail;
        }

        if ($recipients === []) {
            return ActionResult::failed('Keine Empfänger für Vorab-Mail', retryable: false);
        }

        $flow = $context->flow->loadMissing(['type']);
        $betreff = 'Vorab-Info | '.FlowTitle::for($flow);
        $austrittsdatum = trim((string) $context->payloadValue('austrittsdatum', ''));
        $inhalt = 'Vorab-Information zum Austritt'
            .($austrittsdatum !== '' ? " (Austrittsdatum: {$austrittsdatum})" : '')
            .'.';

        if ($dry = PhaseDGuard::assertNotDryRunOrMessage(
            actionLabel: 'Vorab-Mail an '.implode(', ', $recipients),
            output: ['vorab_recipients' => $recipients, 'subject' => $betreff],
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];

        foreach ($recipients as $index => $email) {
            try {
                Mail::to($email)->later(
                    now()->addSeconds(($index + 1) * 2),
                    new WorkflowTicketMail($betreff, $inhalt, $context->payload),
                );
                $messages[] = "Vorab-Mail an {$email} geplant";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$email}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Vorab-Mails fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Vorab-Mails teilweise geplant',
                messages: $messages,
                errors: $errors,
                output: ['vorab_recipients' => $recipients],
            );
        }

        return ActionResult::succeeded(
            message: 'Vorab-Mails geplant',
            messages: $messages,
            output: [
                'vorab_recipients' => $recipients,
                'subject' => $betreff,
            ],
        );
    }
}
