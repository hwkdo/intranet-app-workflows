<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Cisco;

use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class HostCiscoPickupGateway implements CiscoPickupGatewayInterface
{
    public function __construct(
        private readonly AxlServiceInterface $axl,
    ) {}

    public function linePatternForUser(Model $user): ?string
    {
        try {
            if (! $user instanceof Authenticatable) {
                return null;
            }

            $pattern = trim((string) $this->axl->getLinePatternForUser($user));

            return $pattern !== '' ? $pattern : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getPickupGroupForUser(Model $user): ?array
    {
        try {
            $pattern = $this->linePatternForUser($user);
            if ($pattern === null) {
                return null;
            }

            // Lose Line: nur Pattern, kein Phone/End-User nötig.
            $this->axl->getLine($pattern);
            $group = $this->axl->getLinePickupGroup($pattern);

            return is_array($group) ? $group : ['name' => null];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function setPickupGroupForUser(Model $user, string $pickupGroupName): bool
    {
        try {
            $pattern = $this->linePatternForUser($user);
            if ($pattern === null) {
                return false;
            }

            // Lose Line: DN muss existieren; associated Devices/User sind egal.
            $this->axl->getLine($pattern);
            $this->axl->setLinePickupGroupName($pattern, $pickupGroupName);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
