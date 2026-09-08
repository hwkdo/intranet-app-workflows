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

final class DeactivateAdUserAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.deactivate_ad_user';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = $this->resolveUsername($context);
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("AD-User [{$username}] deaktivieren")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, ['ad_user_deactivated' => false]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->ldap->deactivateUser($username)) {
                return ActionResult::failed("AD-User [{$username}] konnte nicht deaktiviert werden");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('AD-Deaktivierung fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "AD-User [{$username}] deaktiviert",
            output: ['ad_user_deactivated' => true],
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
