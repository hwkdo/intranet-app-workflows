<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenSendGatewayInterface;
use RuntimeException;

final class HostBitwardenSendGateway implements BitwardenSendGatewayInterface
{
    public function createSend(
        string $name,
        string $content,
        ?int $maxAccessCount = null,
        ?int $deleteInDays = null,
    ): string {
        $result = app(HwkAdminService::class)->createBitwardenSend(
            $name,
            $content,
            $maxAccessCount,
            $deleteInDays,
        );

        if (! is_string($result) || $result === '' || ! str_starts_with($result, 'http')) {
            throw new RuntimeException('Bitwarden Send lieferte keine gültige URL.');
        }

        return $result;
    }
}
