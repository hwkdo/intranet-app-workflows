<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Ldap;

use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

/**
 * Fallback ohne LDAP (lokale Dev ohne AD) bzw. Test-Double.
 */
final class NullLdapIdentityGateway implements LdapIdentityGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var list<string> */
    public array $existingUsernames = [];

    /** @var list<string> */
    public array $importedUsernames = [];

    public bool $passwordOpsSupported = true;

    /** @var array<string, array{mail?: ?string, given_name?: ?string, sn?: ?string, proxy_addresses?: list<string>, mail_nickname?: ?string, target_address?: ?string, recipient_type_details?: ?string}> */
    public array $remoteMailboxStateByUsername = [];

    /** @var list<string> */
    public array $disabledUsernames = [];

    public function usernameExists(string $username): bool
    {
        $this->calls[] = ['op' => 'usernameExists', 'args' => [$username]];

        return in_array($username, $this->existingUsernames, true);
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
        $this->calls[] = ['op' => 'createUser', 'args' => func_get_args()];
        $this->existingUsernames[] = $username;

        return true;
    }

    /** @var array<string, list<string>> */
    public array $groupsByUsername = [];

    public function addUserToGroup(string $username, string $groupname): bool
    {
        $this->calls[] = ['op' => 'addUserToGroup', 'args' => [$username, $groupname]];
        $this->groupsByUsername[$username] ??= [];
        if (! in_array($groupname, $this->groupsByUsername[$username], true)) {
            $this->groupsByUsername[$username][] = $groupname;
        }

        return true;
    }

    public function removeUserFromGroup(string $username, string $groupname): bool
    {
        $this->calls[] = ['op' => 'removeUserFromGroup', 'args' => [$username, $groupname]];
        $this->groupsByUsername[$username] = array_values(array_filter(
            $this->groupsByUsername[$username] ?? [],
            static fn (string $g): bool => $g !== $groupname,
        ));

        return true;
    }

    public function getUserGroupNames(string $username): array
    {
        $this->calls[] = ['op' => 'getUserGroupNames', 'args' => [$username]];

        return array_values($this->groupsByUsername[$username] ?? []);
    }

    public function setPassword(string $username, string $password): bool
    {
        $this->calls[] = ['op' => 'setPassword', 'args' => [$username, $password]];

        return true;
    }

    public function activateUser(string $username): bool
    {
        $this->calls[] = ['op' => 'activateUser', 'args' => [$username]];

        return true;
    }

    public function deactivateUser(string $username): bool
    {
        $this->calls[] = ['op' => 'deactivateUser', 'args' => [$username]];
        if (! in_array($username, $this->disabledUsernames, true)) {
            $this->disabledUsernames[] = $username;
        }

        return true;
    }

    public function moveUserToOu(string $username, string $ouDn): bool
    {
        $this->calls[] = ['op' => 'moveUserToOu', 'args' => [$username, $ouDn]];

        return true;
    }

    public function clearTelephoneAndFax(string $username): bool
    {
        $this->calls[] = ['op' => 'clearTelephoneAndFax', 'args' => [$username]];

        return true;
    }

    public function removeAllGroups(string $username): bool
    {
        $this->calls[] = ['op' => 'removeAllGroups', 'args' => [$username]];
        $this->groupsByUsername[$username] = [];

        return true;
    }

    public function setChangePasswordAtNextLogon(string $username, bool $value = true): bool
    {
        $this->calls[] = ['op' => 'setChangePasswordAtNextLogon', 'args' => [$username, $value]];

        return true;
    }

    public function importUser(string $username): bool
    {
        $this->calls[] = ['op' => 'importUser', 'args' => [$username]];

        if (! in_array($username, $this->importedUsernames, true)) {
            $this->importedUsernames[] = $username;
        }

        $userClass = WorkflowModels::user();
        if (! $userClass::query()->where('username', $username)->exists() && method_exists($userClass, 'factory')) {
            $userClass::factory()->create([
                'username' => $username,
                'vorname' => 'Import',
                'nachname' => 'Test',
                'active' => true,
            ]);
        }

        return $userClass::query()->where('username', $username)->exists();
    }

    public function supportsPasswordOps(): bool
    {
        return $this->passwordOpsSupported;
    }

    public function isUserEnabled(string $username): ?bool
    {
        $this->calls[] = ['op' => 'isUserEnabled', 'args' => [$username]];

        if (! in_array($username, $this->existingUsernames, true)
            && ! isset($this->remoteMailboxStateByUsername[$username])) {
            return null;
        }

        return ! in_array($username, $this->disabledUsernames, true);
    }

    public function getRemoteMailboxState(string $username): ?array
    {
        $this->calls[] = ['op' => 'getRemoteMailboxState', 'args' => [$username]];

        if (! isset($this->remoteMailboxStateByUsername[$username])
            && ! in_array($username, $this->existingUsernames, true)) {
            return null;
        }

        $state = $this->remoteMailboxStateByUsername[$username] ?? [];

        return [
            'mail' => $state['mail'] ?? null,
            'given_name' => $state['given_name'] ?? null,
            'sn' => $state['sn'] ?? null,
            'proxy_addresses' => array_values($state['proxy_addresses'] ?? []),
            'mail_nickname' => $state['mail_nickname'] ?? null,
            'target_address' => $state['target_address'] ?? null,
            'recipient_type_details' => $state['recipient_type_details'] ?? null,
        ];
    }

    public function applyRemoteMailboxAttributes(string $username, array $attributes): bool
    {
        $this->calls[] = ['op' => 'applyRemoteMailboxAttributes', 'args' => [$username, $attributes]];

        $current = $this->remoteMailboxStateByUsername[$username] ?? [];
        $proxies = $attributes['proxyaddresses'] ?? $attributes['proxyAddresses'] ?? null;

        $this->remoteMailboxStateByUsername[$username] = [
            'mail' => $current['mail'] ?? null,
            'given_name' => $current['given_name'] ?? null,
            'sn' => $current['sn'] ?? null,
            'proxy_addresses' => is_array($proxies) ? array_values($proxies) : ($current['proxy_addresses'] ?? []),
            'mail_nickname' => (string) ($attributes['mailnickname'] ?? $attributes['mailNickname'] ?? $username),
            'target_address' => (string) ($attributes['targetaddress'] ?? $attributes['targetAddress'] ?? ''),
            'recipient_type_details' => (string) ($attributes['msexchrecipienttypedetails'] ?? $attributes['msExchRecipientTypeDetails'] ?? ''),
        ];

        return true;
    }
}
