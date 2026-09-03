<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use App\Models\Gvp;
use App\Models\Standort;
use App\Services\LdapRecordUserService;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

/**
 * Legacy-Parität für ma_neu Schritt 3: Username-Vorschlag + Forced/Analog-LDAP-Gruppen.
 */
final class MaNeuStep3Planner
{
    /**
     * Unveränderbare AD-Gruppen aus Payload (Schritt 1/2) + Config-Defaults.
     *
     * @return list<string>
     */
    public function forcedGroups(WorkflowFlow $flow): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $flow->payload ?? [];
        $groups = [];

        if ($this->isTruthy($payload['farbdruck_benoetigt'] ?? null)) {
            $groups[] = (string) config('intranet-app-workflows.ldap.farbdruck_group');
        }

        $standortId = $payload['standort'] ?? null;
        if ($standortId) {
            $standort = Standort::query()->find($standortId);
            if (filled($standort?->emailverteiler)) {
                $groups[] = (string) $standort->emailverteiler;
            }
        }

        if ($this->isTruthy($payload['istazubi'] ?? null)) {
            $groups = array_merge($groups, config('intranet-app-workflows.ldap.default_azubis', []));
        }

        if ($this->isTruthy($payload['istausbilder'] ?? null)) {
            $groups = array_merge($groups, config('intranet-app-workflows.ldap.default_dozenten_groups', []));
        } else {
            $groups = array_merge($groups, config('intranet-app-workflows.ldap.default_nicht_dozenten', []));
        }

        $gvp = isset($payload['abteilung'])
            ? Gvp::query()->find($payload['abteilung'])
            : null;

        if ($gvp instanceof Gvp) {
            $eduGroups = $this->resolveEduLicense($gvp)
                ? config('intranet-app-workflows.ldap.default_edulicense_true', [])
                : config('intranet-app-workflows.ldap.default_edulicense_false', []);
            $groups = array_merge($groups, $eduGroups);

            $gvpGroup = $this->resolveGvpAdGroupName($gvp);
            if ($gvpGroup !== null) {
                $groups[] = $gvpGroup;
            }
        }

        $groups = array_merge($groups, config('intranet-app-workflows.ldap.default_user_groups', []));

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $g): string => trim((string) $g), $groups),
            static fn (string $g): bool => $g !== '',
        )));
    }

    /**
     * Gruppen des „Laufwerke analog zu“-Users, kategorisiert wie Legacy Step3.
     *
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
        $result = [
            'share' => [],
            'email' => [],
            'd3' => [],
            'other' => [],
            'error' => null,
        ];

        if ($userId === null) {
            $result['error'] = 'Kein Referenz-Mitarbeiter für Laufwerke gewählt.';

            return $result;
        }

        $user = WorkflowModels::userQuery()->find($userId);
        $username = is_object($user) ? trim((string) ($user->username ?? '')) : '';

        if ($username === '') {
            $result['error'] = 'Referenz-Mitarbeiter hat keinen Username.';

            return $result;
        }

        try {
            /** @var list<string> $names */
            $names = array_values(array_filter(
                LdapRecordUserService::GetUserGroupnames2($username),
                static fn (mixed $n): bool => is_string($n) && $n !== '',
            ));
        } catch (Throwable $e) {
            report($e);
            $result['error'] = 'LDAP-Gruppen des Referenz-Users konnten nicht geladen werden.';

            return $result;
        }

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

    public function suggestUsername(): ?string
    {
        try {
            $guess = LdapRecordUserService::guessNextUsername();

            return is_string($guess) && $guess !== '' ? $guess : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return bool|null true=frei, false=belegt, null=Prüfung fehlgeschlagen
     */
    public function isUsernameAvailable(string $username): ?bool
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        try {
            return (bool) LdapRecordUserService::getUsernameIsAvailable($username);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
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
        return array_values(array_unique(array_filter(
            array_merge($share, $email, $d3, $forced),
            static fn (mixed $g): bool => is_string($g) && trim($g) !== '',
        )));
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function resolveEduLicense(Gvp $gvp): bool
    {
        $current = $gvp;

        while ($current instanceof Gvp) {
            if ($current->edulicense !== null) {
                return (bool) $current->edulicense;
            }

            $current = $current->parent_id
                ? Gvp::query()->find($current->parent_id)
                : null;
        }

        return false;
    }

    private function resolveGvpAdGroupName(Gvp $gvp): ?string
    {
        $filter = $this->gvpBezeichnungFilter($gvp);
        $ou = (string) config('intranet-app-workflows.ldap.gvp_adgroups_dn');

        try {
            $group = LdapRecordUserService::getGroup($filter, $ou ?: false);

            if ($group) {
                $cn = $group->getFirstAttribute('cn');

                return is_string($cn) && $cn !== '' ? $cn : $filter;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    private function gvpBezeichnungFilter(Gvp $gvp): string
    {
        $bez = str_replace([',', '/'], '_', (string) $gvp->bezeichnung);

        if (strlen($bez) >= 64) {
            $bez = substr($bez, 0, 63);
        }

        return $bez;
    }
}
