<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

use Illuminate\Support\Collection;

/**
 * Host-Adapter für BuE-Rollen (Phase C).
 */
interface BueRolesGatewayInterface
{
    /**
     * @return Collection<int, string>
     */
    public function getRoles(string $bueUsername): Collection;

    public function grantRole(string $bueUsername, string $role): bool;

    public function revokeRole(string $bueUsername, string $role): bool;
}
