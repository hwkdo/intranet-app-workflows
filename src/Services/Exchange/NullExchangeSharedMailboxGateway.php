<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeSharedMailboxGatewayInterface;

/**
 * Fallback ohne HwkAdmin bzw. Test-Double.
 */
final class NullExchangeSharedMailboxGateway implements ExchangeSharedMailboxGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    public bool $setSharedSucceeds = true;

    public function setShared(string $upn): bool
    {
        $this->calls[] = ['op' => 'setShared', 'args' => [$upn]];

        return $this->setSharedSucceeds;
    }
}
