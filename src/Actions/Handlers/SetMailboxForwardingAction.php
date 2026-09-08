<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\MailboxForwardingGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class SetMailboxForwardingAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly MailboxForwardingGatewayInterface $forwarding,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.set_mailbox_forwarding';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $needed = $context->payloadValue('email_weiterleitung_benoetigt');
        if (! in_array($needed, [1, '1', true], true)) {
            return ActionResult::succeeded(
                message: 'Keine E-Mail-Weiterleitung gewünscht',
                output: ['mailbox_forwarding_skipped' => true],
            );
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $fromUser = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $fromUser) {
            return ActionResult::failed('Mitarbeiter nicht gefunden', retryable: false);
        }

        $username = trim((string) ($fromUser->username ?? $context->payloadValue('username', '')));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $mailboxUpn = trim((string) ($fromUser->email ?? ''));
        if ($mailboxUpn === '' && isset($fromUser->upn)) {
            $mailboxUpn = trim((string) $fromUser->upn);
        }
        if ($mailboxUpn === '' && $username !== '') {
            $mailboxUpn = $username.PhaseBGuard::upnSuffix();
        }
        if ($mailboxUpn === '') {
            return ActionResult::failed('Keine Mailbox-Adresse für Weiterleitung', retryable: false);
        }

        $forwardToId = $context->payloadValue('email_weiterleitung_an');
        if (! is_numeric($forwardToId)) {
            return ActionResult::failed('email_weiterleitung_an fehlt', retryable: false);
        }

        $forwardUser = WorkflowModels::userQuery()->find((int) $forwardToId);
        $forwardSmtp = trim((string) ($forwardUser?->email ?? ''));
        if ($forwardSmtp === '') {
            return ActionResult::failed('Weiterleitungsziel hat keine E-Mail', retryable: false);
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage(
            "Inbox-Weiterleitung [{$mailboxUpn}] → [{$forwardSmtp}]"
        )) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'mailbox_forwarding_set' => false,
                    'mailbox_forwarding_from' => $mailboxUpn,
                    'mailbox_forwarding_to' => $forwardSmtp,
                ]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->forwarding->setForwarding($mailboxUpn, $forwardSmtp)) {
                return ActionResult::failed("Weiterleitung [{$mailboxUpn}] → [{$forwardSmtp}] fehlgeschlagen");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Weiterleitung fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Weiterleitung [{$mailboxUpn}] → [{$forwardSmtp}] gesetzt",
            output: [
                'mailbox_forwarding_set' => true,
                'mailbox_forwarding_from' => $mailboxUpn,
                'mailbox_forwarding_to' => $forwardSmtp,
            ],
        );
    }
}
