<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeQuotaGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Illuminate\Support\Carbon;
use Throwable;

final class SetMailboxQuotaAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly ExchangeQuotaGatewayInterface $exchangeQuota,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.set_mailbox_quota';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $upn = $this->resolveUpn($context, $username);
        $quotas = $this->configuredQuotas();

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage(
            "Mailbox-Quota für [{$upn}]: Send {$quotas['prohibit_send']}, Send/Receive {$quotas['prohibit_send_receive']}, Warning {$quotas['issue_warning']}"
        )) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'mailbox_quota_set' => false,
                    'mailbox_quota_upn' => $upn,
                ]),
                messages: $dry->messages,
            );
        }

        try {
            if (! $this->exchangeQuota->isMailboxReady($upn)) {
                return $this->waitForMailbox($context, $upn);
            }

            if (! $this->exchangeQuota->setQuota(
                $upn,
                $quotas['prohibit_send'],
                $quotas['prohibit_send_receive'],
                $quotas['issue_warning'],
            )) {
                return ActionResult::failed("Mailbox-Quota für [{$upn}] konnte nicht gesetzt werden");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Mailbox-Quota fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Mailbox-Quota für [{$upn}] gesetzt",
            output: [
                'mailbox_quota_set' => true,
                'mailbox_quota_upn' => $upn,
                'mailbox_quota_prohibit_send' => $quotas['prohibit_send'],
                'mailbox_quota_prohibit_send_receive' => $quotas['prohibit_send_receive'],
                'mailbox_quota_issue_warning' => $quotas['issue_warning'],
            ],
            messages: [
                'ProhibitSendQuota: '.$quotas['prohibit_send'],
                'ProhibitSendReceiveQuota: '.$quotas['prohibit_send_receive'],
                'IssueWarningQuota: '.$quotas['issue_warning'],
            ],
        );
    }

    private function resolveUpn(ActionContext $context, string $username): string
    {
        $explicit = trim((string) $context->payloadValue('userprincipalname', ''));
        if ($explicit === '') {
            $explicit = trim((string) $context->payloadValue('upn', ''));
        }

        if ($explicit !== '') {
            return $explicit;
        }

        return $username.PhaseBGuard::upnSuffix();
    }

    /**
     * @return array{prohibit_send: string, prohibit_send_receive: string, issue_warning: string}
     */
    private function configuredQuotas(): array
    {
        return [
            'prohibit_send' => (string) config(
                'intranet-app-workflows.phase_b.mailbox_quota.prohibit_send',
                '4.9GB',
            ),
            'prohibit_send_receive' => (string) config(
                'intranet-app-workflows.phase_b.mailbox_quota.prohibit_send_receive',
                '5GB',
            ),
            'issue_warning' => (string) config(
                'intranet-app-workflows.phase_b.mailbox_quota.issue_warning',
                '4.5GB',
            ),
        ];
    }

    private function waitForMailbox(ActionContext $context, string $upn): ActionResult
    {
        $startedAt = $context->payloadValue('mailbox_quota_wait_started_at');
        if (! is_string($startedAt) || $startedAt === '') {
            $startedAt = now()->toIso8601String();
        }

        $timeoutHours = max(1, (int) config(
            'intranet-app-workflows.phase_b.mailbox_quota.timeout_hours',
            24,
        ));
        $pollMinutes = max(1, (int) config(
            'intranet-app-workflows.phase_b.mailbox_quota.poll_minutes',
            15,
        ));

        $started = Carbon::parse($startedAt);
        if ($started->copy()->addHours($timeoutHours)->isPast()) {
            return ActionResult::failed(
                message: "Timeout: Exchange-Mailbox für [{$upn}] innerhalb von {$timeoutHours}h nicht bereit",
                errors: ['getExchangeQuota lieferte keine Quota – Mailbox vermutlich noch nicht provisioniert'],
                retryable: true,
            );
        }

        $until = now()->addMinutes($pollMinutes);

        return ActionResult::waiting(
            message: "Warte auf Exchange-Mailbox für Quota [{$upn}] – nächster Check ".$until->format('d.m.Y H:i'),
            until: $until,
            output: ['mailbox_quota_wait_started_at' => $startedAt],
            messages: [
                'Mailbox muss in Exchange Online erreichbar sein, bevor Quotas gesetzt werden.',
            ],
        );
    }
}
