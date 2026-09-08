<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Host-Adapter für Cisco Call Manager Anrufübernahmegruppen (AXL).
 * Arbeitet über das Line-Pattern (Telefon), nicht über CUCM-End-User/Phones.
 */
interface CiscoPickupGatewayInterface
{
    /**
     * Primäres Line-Pattern des Intranet-Users (z. B. aus Telefonnummer).
     */
    public function linePatternForUser(Model $user): ?string;

    /**
     * @return array{name?: string|null}|null null = User/Leitung nicht ermittelbar
     */
    public function getPickupGroupForUser(Model $user): ?array;

    public function setPickupGroupForUser(Model $user, string $pickupGroupName): bool;
}
