<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\HwkAdminLaravel\DTO\GetExchangeQuotaOutputDTO;
use Hwkdo\HwkAdminLaravel\DTO\SetExchangeQuotaDTO;
use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeQuotaGatewayInterface;
use Throwable;

final class HostExchangeQuotaGateway implements ExchangeQuotaGatewayInterface
{
    public function isMailboxReady(string $upn): bool
    {
        try {
            $result = app(HwkAdminService::class)->getExchangeQuota($upn);

            return $result instanceof GetExchangeQuotaOutputDTO
                && $result->ProhibitSendQuota !== null
                && $result->ProhibitSendQuota !== '';
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function setQuota(
        string $upn,
        string $prohibitSend,
        string $prohibitSendReceive,
        string $issueWarning,
    ): bool {
        try {
            $result = app(HwkAdminService::class)->setExchangeQuota(SetExchangeQuotaDTO::from([
                'owner_upn' => $upn,
                'ProhibitSendQuota' => $prohibitSend,
                'ProhibitSendReceiveQuota' => $prohibitSendReceive,
                'IssueWarningQuota' => $issueWarning,
            ]));

            if (is_array($result)) {
                return (bool) ($result['successful'] ?? false);
            }

            return (bool) $result;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
