<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bue;

use Hwkdo\BueLaravel\BueLaravel;
use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Illuminate\Support\Collection;

final class HostBueRolesGateway implements BueRolesGatewayInterface
{
    public function __construct(
        private readonly BueLaravel $bue = new BueLaravel,
    ) {}

    public function getRoles(string $bueUsername): Collection
    {
        return $this->bue->getBueRoles($bueUsername);
    }

    public function grantRole(string $bueUsername, string $role): bool
    {
        return $this->bue->grantBueRole($bueUsername, $role);
    }

    public function revokeRole(string $bueUsername, string $role): bool
    {
        return $this->bue->revokeBueRole($bueUsername, $role);
    }
}
