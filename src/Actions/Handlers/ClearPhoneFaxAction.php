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

final class ClearPhoneFaxAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.clear_phone_fax';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = $this->resolveUsername($context);
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("Telefon/Fax für [{$username}] leeren")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, ['phone_fax_cleared' => false]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->ldap->clearTelephoneAndFax($username)) {
                return ActionResult::failed("Telefon/Fax für [{$username}] konnte nicht geleert werden");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Telefon/Fax leeren fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Telefon/Fax für [{$username}] geleert",
            output: ['phone_fax_cleared' => true],
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
