<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;

final class AddLdapGroupsAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.add_ldap_groups';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $groups = $this->normalizeGroups($context->payloadValue('add_ldap_groups'));
        if ($groups === []) {
            return ActionResult::succeeded('Keine LDAP-Gruppen zu setzen');
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage(
            'LDAP-Gruppen für ['.$username.']: '.count($groups).' geplant'
        )) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, ['ldap_groups_planned' => $groups]),
                messages: array_merge($dry->messages, $groups),
            );
        }

        $ok = [];
        $errors = [];
        foreach ($groups as $group) {
            if ($this->ldap->addUserToGroup($username, $group)) {
                $ok[] = $group;
            } else {
                $errors[] = $group;
            }
        }

        if ($errors !== [] && $ok === []) {
            return ActionResult::failed(
                message: 'Keine LDAP-Gruppe konnte gesetzt werden',
                errors: $errors,
            );
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'LDAP-Gruppen teilweise gesetzt',
                messages: array_map(fn (string $g): string => "OK: {$g}", $ok),
                errors: array_map(fn (string $g): string => "Fehler: {$g}", $errors),
                output: ['ldap_groups_added' => $ok, 'ldap_groups_failed' => $errors],
            );
        }

        return ActionResult::succeeded(
            message: count($ok).' LDAP-Gruppe(n) gesetzt',
            output: ['ldap_groups_added' => $ok],
            messages: array_map(fn (string $g): string => "OK: {$g}", $ok),
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeGroups(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (! is_array($raw)) {
            return [];
        }

        $groups = [];
        foreach ($raw as $group) {
            if (! is_string($group)) {
                continue;
            }
            $group = trim($group);
            if ($group !== '') {
                $groups[] = $group;
            }
        }

        return array_values(array_unique($groups));
    }
}
