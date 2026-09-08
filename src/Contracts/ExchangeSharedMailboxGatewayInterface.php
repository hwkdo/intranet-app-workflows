<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter: Exchange-Mailbox zu Shared konvertieren (HwkAdmin).
 */
interface ExchangeSharedMailboxGatewayInterface
{
    public function setShared(string $upn): bool;
}
