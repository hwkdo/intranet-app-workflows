<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeSharedMailboxGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class ConvertMailboxSharedAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly ExchangeSharedMailboxGatewayInterface $sharedMailbox,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.convert_mailbox_shared';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $resolved = $this->resolveUpn($context);
        if ($resolved instanceof ActionResult) {
            return $resolved;
        }

        [$username, $upn] = $resolved;

        if ($early = PhaseBGuard::preflight($username)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("Mailbox [{$upn}] zu Shared konvertieren")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'mailbox_shared' => false,
                    'mailbox_shared_upn' => $upn,
                ]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->sharedMailbox->setShared($upn)) {
                return ActionResult::failed("Mailbox [{$upn}] konnte nicht zu Shared konvertiert werden");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Shared-Mailbox fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Mailbox [{$upn}] zu Shared konvertiert",
            output: [
                'mailbox_shared' => true,
                'mailbox_shared_upn' => $upn,
            ],
        );
    }

    /**
     * @return array{0: string, 1: string}|ActionResult
     */
    private function resolveUpn(ActionContext $context): array|ActionResult
    {
        $explicit = trim((string) $context->payloadValue('userprincipalname', ''));
        if ($explicit === '') {
            $explicit = trim((string) $context->payloadValue('upn', ''));
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            $mitarbeiterId = $context->payloadValue('mitarbeiter');
            if (is_numeric($mitarbeiterId)) {
                $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
                $username = trim((string) ($user?->username ?? ''));
                if ($explicit === '' && $user) {
                    $email = trim((string) ($user->email ?? ''));
                    if ($email !== '') {
                        $explicit = $email;
                    } elseif (isset($user->upn)) {
                        $explicit = trim((string) $user->upn);
                    }
                }
            }
        }

        if ($username === '' && $explicit === '') {
            return ActionResult::failed('Username/UPN für Shared-Mailbox fehlt', retryable: false);
        }

        if ($explicit === '') {
            $explicit = $username.PhaseBGuard::upnSuffix();
        }

        if ($username === '') {
            $username = strstr($explicit, '@', true) ?: $explicit;
        }

        return [$username, $explicit];
    }
}
