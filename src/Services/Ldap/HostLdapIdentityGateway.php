<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Ldap;

use App\Services\LdapRecordUserService;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class HostLdapIdentityGateway implements LdapIdentityGatewayInterface
{
    public function usernameExists(string $username): bool
    {
        return LdapRecordUserService::getUser($username) !== null;
    }

    public function createUser(
        int|string $standortId,
        string $username,
        string $upnSuffix,
        string $vorname,
        string $nachname,
        string $telefon,
        string $fax,
        string $raum,
        string $personalnr,
        string $homeshareLetter,
    ): mixed {
        return LdapRecordUserService::makeUser(
            $standortId,
            $username,
            $upnSuffix,
            $vorname,
            $nachname,
            $telefon,
            $fax,
            $raum,
            $personalnr,
            $homeshareLetter,
        );
    }

    public function addUserToGroup(string $username, string $groupname): bool
    {
        try {
            return (bool) LdapRecordUserService::addUserToGroup($username, $groupname);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function removeUserFromGroup(string $username, string $groupname): bool
    {
        try {
            return (bool) LdapRecordUserService::removeUserFromGroup($username, $groupname);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function getUserGroupNames(string $username): array
    {
        try {
            /** @var list<string> $names */
            $names = array_values(array_filter(
                LdapRecordUserService::GetUserGroupnames2($username),
                static fn (mixed $n): bool => is_string($n) && $n !== '',
            ));

            return $names;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    public function setPassword(string $username, string $password): bool
    {
        try {
            $result = LdapRecordUserService::setPassword($username, $password);

            // LdapRecord save() liefert hier oft null trotz erfolgreichem Write.
            return $result !== false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function activateUser(string $username): bool
    {
        try {
            return LdapRecordUserService::activateUser($username) !== null;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function deactivateUser(string $username): bool
    {
        try {
            return LdapRecordUserService::deactivateUser($username) !== null;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function moveUserToOu(string $username, string $ouDn): bool
    {
        try {
            return LdapRecordUserService::moveToOu($username, $ouDn);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function clearTelephoneAndFax(string $username): bool
    {
        try {
            return LdapRecordUserService::clearTelephoneAndFax($username);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function removeAllGroups(string $username): bool
    {
        $groups = $this->getUserGroupNames($username);
        if ($groups === []) {
            return true;
        }

        $allOk = true;
        foreach ($groups as $group) {
            if (! $this->removeUserFromGroup($username, $group)) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    public function setChangePasswordAtNextLogon(string $username, bool $value = true): bool
    {
        try {
            $result = LdapRecordUserService::setChangePwAtNextLogon($username, $value);

            return $result !== false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function importUser(string $username): bool
    {
        $result = LdapRecordUserService::importLdapUser($username);

        return (bool) $result
            && WorkflowModels::userQuery()->where('username', $username)->exists();
    }

    public function supportsPasswordOps(): bool
    {
        return (bool) config('ldap.connections.default.use_ssl')
            || (bool) config('ldap.connections.default.use_tls');
    }

    public function isUserEnabled(string $username): ?bool
    {
        try {
            $user = LdapRecordUserService::getUser($username);
            if ($user === null) {
                return null;
            }

            return (bool) $user->isEnabled();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getRemoteMailboxState(string $username): ?array
    {
        $user = LdapRecordUserService::getUser($username);
        if ($user === null) {
            return null;
        }

        $proxies = $user->getAttribute('proxyaddresses') ?? $user->getAttribute('proxyAddresses') ?? [];
        if (! is_array($proxies)) {
            $proxies = [$proxies];
        }

        return [
            'mail' => $user->getFirstAttribute('mail'),
            'given_name' => $user->getFirstAttribute('givenname') ?? $user->getFirstAttribute('givenName'),
            'sn' => $user->getFirstAttribute('sn'),
            'proxy_addresses' => array_values(array_filter(
                array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $proxies),
            )),
            'mail_nickname' => $user->getFirstAttribute('mailnickname') ?? $user->getFirstAttribute('mailNickname'),
            'target_address' => $user->getFirstAttribute('targetaddress') ?? $user->getFirstAttribute('targetAddress'),
            'recipient_type_details' => $user->getFirstAttribute('msexchrecipienttypedetails')
                ?? $user->getFirstAttribute('msExchRecipientTypeDetails'),
        ];
    }

    public function applyRemoteMailboxAttributes(string $username, array $attributes): bool
    {
        try {
            $user = LdapRecordUserService::getUser($username);
            if ($user === null) {
                return false;
            }

            foreach ($attributes as $key => $value) {
                $user->setAttribute((string) $key, $value);
            }

            $result = $user->save();

            return $result !== false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
