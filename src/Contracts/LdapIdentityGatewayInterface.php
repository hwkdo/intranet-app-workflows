<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter für AD/LDAP-Identity-Operationen (Phase B).
 */
interface LdapIdentityGatewayInterface
{
    public function usernameExists(string $username): bool;

    /**
     * @return object|bool|null  Legacy: AD-User-Objekt oder truthy bei Erfolg
     */
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
    ): mixed;

    public function addUserToGroup(string $username, string $groupname): bool;

    public function setPassword(string $username, string $password): bool;

    public function activateUser(string $username): bool;

    public function setChangePasswordAtNextLogon(string $username, bool $value = true): bool;

    public function importUser(string $username): bool;

    public function supportsPasswordOps(): bool;
}
