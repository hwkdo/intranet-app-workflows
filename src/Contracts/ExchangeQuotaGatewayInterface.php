<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter für Exchange Online Mailbox-Quotas (HwkAdmin).
 */
interface ExchangeQuotaGatewayInterface
{
    /**
     * true, wenn die Mailbox in Exchange Online bereits lesbar ist.
     */
    public function isMailboxReady(string $upn): bool;

    /**
     * @param  string  $prohibitSend  z. B. "4.9GB"
     * @param  string  $prohibitSendReceive  z. B. "5GB"
     * @param  string  $issueWarning  z. B. "4.5GB"
     */
    public function setQuota(
        string $upn,
        string $prohibitSend,
        string $prohibitSendReceive,
        string $issueWarning,
    ): bool;
}
