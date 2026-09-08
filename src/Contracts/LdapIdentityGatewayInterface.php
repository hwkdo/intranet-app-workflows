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

    public function removeUserFromGroup(string $username, string $groupname): bool;

    /**
     * @return list<string>
     */
    public function getUserGroupNames(string $username): array;

    public function setPassword(string $username, string $password): bool;

    public function activateUser(string $username): bool;

    public function deactivateUser(string $username): bool;

    public function moveUserToOu(string $username, string $ouDn): bool;

    public function clearTelephoneAndFax(string $username): bool;

    /**
     * Entfernt alle AD-Gruppenmitgliedschaften. Fehlschläge werden übersprungen (weiter versucht).
     */
    public function removeAllGroups(string $username): bool;

    public function setChangePasswordAtNextLogon(string $username, bool $value = true): bool;

    public function importUser(string $username): bool;

    public function supportsPasswordOps(): bool;

    /**
     * null = User nicht gefunden, true/false = Konto aktiviert/deaktiviert.
     */
    public function isUserEnabled(string $username): ?bool;

    /**
     * @return array{
     *     mail: ?string,
     *     given_name: ?string,
     *     sn: ?string,
     *     proxy_addresses: list<string>,
     *     mail_nickname: ?string,
     *     target_address: ?string,
     *     recipient_type_details: ?string
     * }|null  null = User nicht gefunden
     */
    public function getRemoteMailboxState(string $username): ?array;

    /**
     * @param  array<string, mixed>  $attributes  LDAP-Attributnamen (lowercase ok)
     */
    public function applyRemoteMailboxAttributes(string $username, array $attributes): bool;
}
