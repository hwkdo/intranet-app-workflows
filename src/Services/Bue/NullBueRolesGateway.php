<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bue;

use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Illuminate\Support\Collection;

/**
 * Fallback ohne BuE-DB / Test-Double.
 */
final class NullBueRolesGateway implements BueRolesGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var array<string, list<string>> */
    public array $rolesByUser = [];

    public function getRoles(string $bueUsername): Collection
    {
        $this->calls[] = ['op' => 'getRoles', 'args' => [$bueUsername]];

        return collect($this->rolesByUser[$bueUsername] ?? []);
    }

    public function grantRole(string $bueUsername, string $role): bool
    {
        $this->calls[] = ['op' => 'grantRole', 'args' => [$bueUsername, $role]];
        $this->rolesByUser[$bueUsername] ??= [];
        if (! in_array($role, $this->rolesByUser[$bueUsername], true)) {
            $this->rolesByUser[$bueUsername][] = $role;
        }

        return true;
    }

    public function revokeRole(string $bueUsername, string $role): bool
    {
        $this->calls[] = ['op' => 'revokeRole', 'args' => [$bueUsername, $role]];
        $this->rolesByUser[$bueUsername] = array_values(array_filter(
            $this->rolesByUser[$bueUsername] ?? [],
            static fn (string $existing): bool => $existing !== $role,
        ));

        return true;
    }
}
