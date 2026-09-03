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

    public function setPassword(string $username, string $password): bool
    {
        return (bool) LdapRecordUserService::setPassword($username, $password);
    }

    public function activateUser(string $username): bool
    {
        return LdapRecordUserService::activateUser($username) !== null;
    }

    public function setChangePasswordAtNextLogon(string $username, bool $value = true): bool
    {
        return (bool) LdapRecordUserService::setChangePwAtNextLogon($username, $value);
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
}
