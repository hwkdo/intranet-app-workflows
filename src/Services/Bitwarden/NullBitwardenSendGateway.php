<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenSendGatewayInterface;
use RuntimeException;

final class NullBitwardenSendGateway implements BitwardenSendGatewayInterface
{
    public function createSend(
        string $name,
        string $content,
        ?int $maxAccessCount = null,
        ?int $deleteInDays = null,
    ): string {
        throw new RuntimeException('Bitwarden Send ist in dieser Umgebung nicht verfügbar.');
    }
}
