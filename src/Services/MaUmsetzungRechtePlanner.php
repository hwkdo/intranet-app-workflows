<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

/**
 * LDAP- und Intranet-Rollen-Diff für ma_umsetzung IT-Rechte-Step.
 */
final class MaUmsetzungRechtePlanner
{
    public function __construct(
        private readonly MaNeuStep3Planner $ldapPlanner,
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    /**
     * @return list<string>
     */
    public function forcedGroups(WorkflowFlow $flow): array
    {
        return $this->ldapPlanner->forcedGroups($flow);
    }

    /**
     * @return array{
     *     share: list<string>,
     *     email: list<string>,
     *     d3: list<string>,
     *     other: list<string>,
     *     error: ?string
     * }
     */
    public function analogGroups(?int $userId): array
    {
        return $this->ldapPlanner->analogGroups($userId);
    }

    /**
     * @return array{
     *     share: list<string>,
     *     email: list<string>,
     *     d3: list<string>,
     *     other: list<string>,
     *     all: list<string>,
     *     error: ?string
     * }
     */
    public function currentGroups(string $username): array
    {
        $result = [
            'share' => [],
            'email' => [],
            'd3' => [],
            'other' => [],
            'all' => [],
            'error' => null,
        ];

        if ($username === '') {
            $result['error'] = 'Kein Username für aktuelle LDAP-Gruppen.';

            return $result;
        }

        try {
            $names = $this->ldap->getUserGroupNames($username);
        } catch (Throwable $e) {
            report($e);
            $result['error'] = 'Aktuelle LDAP-Gruppen konnten nicht geladen werden.';

            return $result;
        }

        $result['all'] = $names;
        foreach ($names as $name) {
            $lower = strtolower($name);
            if (str_contains($lower, 'share')) {
                $result['share'][] = $name;
            } elseif (str_contains($lower, '_ev_')) {
                $result['email'][] = $name;
            } elseif (str_contains($lower, '_re_') || str_contains($lower, '_d3_')) {
                $result['d3'][] = $name;
            } else {
                $result['other'][] = $name;
            }
        }

        return $result;
    }

    /**
     * Rollen des Analog-Users, die der MA noch nicht hat.
     *
     * @return list<string>
     */
    public function analogIntranetRoles(?int $analogUserId, ?int $mitarbeiterId): array
    {
        if ($analogUserId === null) {
            return [];
        }

        $analog = WorkflowModels::userQuery()->find($analogUserId);
        if (! $analog || ! method_exists($analog, 'getRoleNames')) {
            return [];
        }

        /** @var list<string> $analogRoles */
        $analogRoles = $analog->getRoleNames()->map(strval(...))->all();

        $current = [];
        if ($mitarbeiterId !== null) {
            $ma = WorkflowModels::userQuery()->find($mitarbeiterId);
            if ($ma && method_exists($ma, 'getRoleNames')) {
                $current = $ma->getRoleNames()->map(strval(...))->all();
            }
        }

        return array_values(array_diff($analogRoles, $current));
    }

    /**
     * Aktuelle Rollen des MA ohne geschützte Rollen.
     *
     * @return list<string>
     */
    public function currentIntranetRoles(?int $mitarbeiterId): array
    {
        if ($mitarbeiterId === null) {
            return [];
        }

        $ma = WorkflowModels::userQuery()->find($mitarbeiterId);
        if (! $ma || ! method_exists($ma, 'getRoleNames')) {
            return [];
        }

        $protected = array_map(
            strval(...),
            (array) config('intranet-app-workflows.phase_c.protected_intranet_roles', []),
        );

        /** @var list<string> $roles */
        $roles = $ma->getRoleNames()->map(strval(...))->all();

        return array_values(array_filter(
            $roles,
            static fn (string $role): bool => ! in_array($role, $protected, true),
        ));
    }

    /**
     * @param  list<string>  $share
     * @param  list<string>  $email
     * @param  list<string>  $d3
     * @param  list<string>  $forced
     * @return list<string>
     */
    public function mergeSelectedGroups(array $share, array $email, array $d3, array $forced): array
    {
        return $this->ldapPlanner->mergeSelectedGroups($share, $email, $d3, $forced);
    }
}
