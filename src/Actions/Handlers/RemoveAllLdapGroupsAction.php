<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

final class RemoveAllLdapGroupsAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.remove_all_ldap_groups';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = $this->resolveUsername($context);
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $groups = $this->ldap->getUserGroupNames($username);

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage(
            'Alle LDAP-Gruppen entfernen für ['.$username.']: '.count($groups).' geplant'
        )) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, ['ldap_groups_remove_all_planned' => $groups]),
                messages: array_merge($dry->messages, $groups),
            );
        }

        if ($groups === []) {
            return ActionResult::succeeded(
                message: 'Keine LDAP-Gruppen vorhanden',
                output: ['ldap_groups_removed' => []],
            );
        }

        $ok = $this->ldap->removeAllGroups($username);
        if (! $ok) {
            $remaining = $this->ldap->getUserGroupNames($username);

            return ActionResult::partial(
                message: 'LDAP-Gruppen teilweise entfernt',
                messages: array_map(static fn (string $g): string => "Geplant: {$g}", $groups),
                errors: array_map(static fn (string $g): string => "Verblieben: {$g}", $remaining),
                output: [
                    'ldap_groups_remove_planned' => $groups,
                    'ldap_groups_remaining' => $remaining,
                ],
            );
        }

        return ActionResult::succeeded(
            message: count($groups).' LDAP-Gruppe(n) entfernt',
            output: ['ldap_groups_removed' => $groups],
            messages: array_map(static fn (string $g): string => "OK: {$g}", $groups),
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
