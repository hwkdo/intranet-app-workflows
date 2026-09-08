<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Exchange;

use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppWorkflows\Contracts\MailboxForwardingGatewayInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface;
use Throwable;

/**
 * Setzt ForwardingSmtpAddress über HwkAdmin/Exchange.
 * Entfernt best-effort die alte Graph-Inbox-Rule, falls noch vorhanden.
 */
final class HostMailboxForwardingGateway implements MailboxForwardingGatewayInterface
{
    public function setForwarding(string $mailboxUpn, string $forwardToSmtp): bool
    {
        try {
            $ok = app(HwkAdminService::class)->setMailboxForwarding($mailboxUpn, $forwardToSmtp);
            if (! $ok) {
                return false;
            }

            $this->clearLegacyGraphInboxRule($mailboxUpn);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function clearLegacyGraphInboxRule(string $mailboxUpn): void
    {
        try {
            if (! interface_exists(MsGraphMailboxServiceInterface::class)
                || ! app()->bound(MsGraphMailboxServiceInterface::class)) {
                return;
            }

            app(MsGraphMailboxServiceInterface::class)->clearInboxForwarding($mailboxUpn);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
