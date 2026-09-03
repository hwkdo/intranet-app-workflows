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

    public function addUserToGroup(string $username, string $groupname): bool
    {
        $this->calls[] = ['op' => 'addUserToGroup', 'args' => [$username, $groupname]];

        return true;
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
}
