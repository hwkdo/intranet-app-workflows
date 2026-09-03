<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Throwable;

final class CreateAdUserAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.create_ad_user';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("AD-User [{$username}] anlegen")) {
            return $dry;
        }

        if ($this->ldap->usernameExists($username)) {
            return ActionResult::failed("AD-User [{$username}] existiert bereits", retryable: false);
        }

        $standortId = $context->payloadValue('standort');
        if ($standortId === null || $standortId === '') {
            return ActionResult::failed('Standort fehlt im Payload', retryable: false);
        }

        try {
            $result = $this->ldap->createUser(
                standortId: $standortId,
                username: $username,
                upnSuffix: PhaseBGuard::upnSuffix(),
                vorname: (string) $context->payloadValue('vorname', ''),
                nachname: (string) $context->payloadValue('nachname', ''),
                telefon: (string) $context->payloadValue('telefon', ''),
                fax: (string) $context->payloadValue('fax', ''),
                raum: (string) $context->payloadValue('raum', ''),
                personalnr: (string) $context->payloadValue('personalnr', ''),
                homeshareLetter: PhaseBGuard::homeshareLetter(),
            );
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('AD-User anlegen fehlgeschlagen: '.$e->getMessage());
        }

        if (! $result) {
            return ActionResult::failed("AD-User [{$username}] konnte nicht angelegt werden");
        }

        return ActionResult::succeeded(
            message: "AD-User [{$username}] angelegt",
            output: ['ad_user_created' => true],
        );
    }
}
