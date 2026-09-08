<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Throwable;

final class ActivateAdUserAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.activate_ad_user';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $password = $this->generatePassword();

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("AD-User [{$username}] aktivieren + Passwort setzen")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'password' => $password,
                    'ad_user_activated' => false,
                ]),
                messages: array_merge($dry->messages, ['Passwort (nur Dry-Run / Preview): '.$password]),
            );
        }

        if (! $this->ldap->supportsPasswordOps()) {
            return ActionResult::skipped('Keine LDAP SSL/TLS-Verbindung – Activate+PW übersprungen');
        }

        try {
            if (! $this->ldap->setPassword($username, $password)) {
                return ActionResult::failed("Passwort für [{$username}] konnte nicht gesetzt werden");
            }

            if (! $this->ldap->activateUser($username)) {
                return ActionResult::failed("AD-User [{$username}] konnte nicht aktiviert werden");
            }

            $this->ldap->setChangePasswordAtNextLogon($username, true);
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Activate+PW fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "AD-User [{$username}] aktiviert",
            output: [
                'password' => $password,
                'ad_user_activated' => true,
            ],
            messages: ["AD-User [{$username}] aktiviert (Passwort gesetzt, Change@NextLogon)"],
        );
    }

    private function generatePassword(): string
    {
        // AD-Komplexität: Groß/Klein/Ziffer/Sonderzeichen
        return 'HwK-'.fake()->lexify('????').'-'.now()->format('y').'!'.fake()->numerify('##');
    }
}