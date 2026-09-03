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

final class ImportLdapUserAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.import_ldap_user';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("Intranet-User [{$username}] aus LDAP importieren")) {
            return $dry;
        }

        try {
            $imported = $this->ldap->importUser($username);
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('LDAP-Import fehlgeschlagen: '.$e->getMessage());
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        if (! $imported || ! $user) {
            return ActionResult::failed("LdapUser [{$username}] konnte nicht importiert werden");
        }

        $messages = ["LdapUser [{$username}] zu User ID {$user->id} importiert"];

        if ($this->isTruthy($context->payloadValue('cms_benoetigt'))) {
            $user->cms_redaktion = true;
            $messages[] = "User ID {$user->id}: cms_redaktion gesetzt";
        }

        if ($this->isTruthy($context->payloadValue('istazubi'))) {
            $user->azubi = true;
            $messages[] = "User ID {$user->id}: azubi gesetzt";
        }

        if ($this->isTruthy($context->payloadValue('istpraktikant'))) {
            $user->praktikant = true;
            $messages[] = "User ID {$user->id}: praktikant gesetzt";
        }

        if ($abteilung = $context->payloadValue('abteilung')) {
            $user->gvp_id = (int) $abteilung;
            $messages[] = "User ID {$user->id}: gvp_id gesetzt";
        }

        if ($standort = $context->payloadValue('standort')) {
            $user->standort_id = (int) $standort;
            $messages[] = "User ID {$user->id}: standort_id gesetzt";
        }

        if ($personalnr = $context->payloadValue('personalnr')) {
            $user->personalnr = (string) $personalnr;
            $messages[] = "User ID {$user->id}: personalnr gesetzt";
        }

        $user->save();

        return ActionResult::succeeded(
            message: "Intranet-User [{$username}] importiert (#{$user->id})",
            output: [
                'intranet_user_id' => $user->id,
                'intranet_user_imported' => true,
            ],
            messages: $messages,
        );
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
