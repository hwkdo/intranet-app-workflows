<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeSharedMailboxGatewayInterface;
use Throwable;

final class HostExchangeSharedMailboxGateway implements ExchangeSharedMailboxGatewayInterface
{
    public function setShared(string $upn): bool
    {
        try {
            return app(HwkAdminService::class)->setMailboxShared($upn);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
