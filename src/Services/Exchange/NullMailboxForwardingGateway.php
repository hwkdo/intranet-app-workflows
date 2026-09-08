<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\IntranetAppWorkflows\Contracts\MailboxForwardingGatewayInterface;

/**
 * Fallback ohne MS Graph bzw. Test-Double.
 */
final class NullMailboxForwardingGateway implements MailboxForwardingGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    public bool $setForwardingSucceeds = true;

    public function setForwarding(string $mailboxUpn, string $forwardToSmtp): bool
    {
        $this->calls[] = ['op' => 'setForwarding', 'args' => [$mailboxUpn, $forwardToSmtp]];

        return $this->setForwardingSucceeds;
    }
}
