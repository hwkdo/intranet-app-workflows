<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter: Bitwarden-Mitglied per E-Mail entfernen.
 */
interface BitwardenOffboardGatewayInterface
{
    /**
     * true wenn gelöscht oder kein Mitglied mit dieser E-Mail gefunden.
     */
    public function offboardByEmail(string $email): bool;
}
