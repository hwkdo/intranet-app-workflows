<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class MoveToAustrittOuAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.move_to_austritt_ou';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = $this->resolveUsername($context);
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $ouDn = (string) config(
            'intranet-app-workflows.austritt.ou_dn',
            'OU=Austritt,OU=hwkdo,DC=hwkdo,DC=local',
        );

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("AD-User [{$username}] nach [{$ouDn}] verschieben")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'moved_to_ou' => false,
                    'austritt_ou_dn' => $ouDn,
                ]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->ldap->moveUserToOu($username, $ouDn)) {
                return ActionResult::failed("AD-User [{$username}] konnte nicht nach [{$ouDn}] verschoben werden");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('OU-Move fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "AD-User [{$username}] nach Austritt-OU verschoben",
            output: [
                'moved_to_ou' => true,
                'austritt_ou_dn' => $ouDn,
            ],
        );
    }

    private function resolveUsername(ActionContext $context): string
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($username !== '') {
            return $username;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return '';
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);

        return trim((string) ($user?->username ?? ''));
    }
}
