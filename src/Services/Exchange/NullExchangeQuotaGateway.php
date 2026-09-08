<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeQuotaGatewayInterface;

/**
 * Fallback ohne HwkAdmin bzw. Test-Double.
 */
final class NullExchangeQuotaGateway implements ExchangeQuotaGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var list<string> */
    public array $readyUpns = [];

    public bool $setQuotaSucceeds = true;

    public function isMailboxReady(string $upn): bool
    {
        $this->calls[] = ['op' => 'isMailboxReady', 'args' => [$upn]];

        return in_array($upn, $this->readyUpns, true);
    }

    public function setQuota(
        string $upn,
        string $prohibitSend,
        string $prohibitSendReceive,
        string $issueWarning,
    ): bool {
        $this->calls[] = [
            'op' => 'setQuota',
            'args' => [$upn, $prohibitSend, $prohibitSendReceive, $issueWarning],
        ];

        return $this->setQuotaSucceeds;
    }
}
