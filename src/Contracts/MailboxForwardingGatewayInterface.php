<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter: Exchange-SMTP-Weiterleitung (ForwardingSmtpAddress via HwkAdmin).
 */
interface MailboxForwardingGatewayInterface
{
    public function setForwarding(string $mailboxUpn, string $forwardToSmtp): bool;
}
