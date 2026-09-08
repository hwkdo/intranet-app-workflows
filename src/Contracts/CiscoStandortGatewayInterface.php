<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter für Cisco Phone/Line Standort-Umschaltung (AXL).
 */
interface CiscoStandortGatewayInterface
{
    /**
     * @return list<array{
     *     name: string,
     *     lines: list<array{pattern: string, route_partition: string}>
     * }>
     */
    public function listPhonesForUser(string $userid): array;

    /**
     * Primäres Line-Pattern des Intranet-Users (z. B. aus Telefonnummer), unabhängig von associated Devices.
     */
    public function linePatternForUser(object $user): ?string;

    /**
     * @return array{
     *     name: string,
     *     device_pool: string,
     *     location: string,
     *     reroute_css: string
     * }|null
     */
    public function getPhoneConfig(string $phoneName): ?array;

    public function updatePhoneStandort(
        string $phoneName,
        string $devicePool,
        string $location,
        ?string $rerouteCss,
    ): bool;

    public function applyPhone(string $phoneName): bool;

    /**
     * @return array{pattern: string, calling_search_space: string}|null
     */
    public function getLineConfig(string $pattern): ?array;

    public function updateLineCallingSearchSpace(string $pattern, string $callingSearchSpace): bool;
}
