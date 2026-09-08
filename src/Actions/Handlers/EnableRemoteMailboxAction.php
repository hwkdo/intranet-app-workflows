<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\RemoteMailboxAttributes;
use Illuminate\Support\Carbon;
use Throwable;

final class EnableRemoteMailboxAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.enable_remote_mailbox';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage("Remote-Mailbox-Stamp für [{$username}]")) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, ['remote_mailbox_enabled' => false]),
                messages: $dry->messages,
            );
        }

        try {
            $state = $this->ldap->getRemoteMailboxState($username);
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Remote-Mailbox-Status konnte nicht gelesen werden: '.$e->getMessage());
        }

        if ($state === null) {
            return ActionResult::failed("AD-User [{$username}] nicht gefunden", retryable: true);
        }

        if (RemoteMailboxAttributes::looksEnabled($state)) {
            return ActionResult::succeeded(
                message: "Remote-Mailbox für [{$username}] bereits gesetzt",
                output: ['remote_mailbox_enabled' => true],
            );
        }

        $proxies = $state['proxy_addresses'];
        if (! RemoteMailboxAttributes::hasExchangeLabsX500($proxies)) {
            return $this->waitForCloudMailbox($context, $username);
        }

        $mail = trim((string) ($state['mail'] ?? ''));
        $givenName = trim((string) ($state['given_name'] ?? ''));
        $sn = trim((string) ($state['sn'] ?? ''));

        if ($mail === '' || $givenName === '' || $sn === '') {
            return ActionResult::failed(
                message: "mail/givenName/sn für [{$username}] unvollständig – Stamp abgebrochen",
                retryable: false,
            );
        }

        $attributes = RemoteMailboxAttributes::build(
            $username,
            $mail,
            $givenName,
            $sn,
            $proxies,
        );

        try {
            if (! $this->ldap->applyRemoteMailboxAttributes($username, $attributes)) {
                return ActionResult::failed("Remote-Mailbox-Stamp für [{$username}] fehlgeschlagen");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Remote-Mailbox-Stamp fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Remote-Mailbox für [{$username}] gesetzt",
            output: [
                'remote_mailbox_enabled' => true,
                'remote_mailbox_target_address' => $attributes['targetaddress'],
                'remote_mailbox_proxy_count' => count($attributes['proxyaddresses']),
            ],
            messages: [
                'targetAddress: '.$attributes['targetaddress'],
                'proxyAddresses: '.count($attributes['proxyaddresses']),
            ],
        );
    }

    private function waitForCloudMailbox(ActionContext $context, string $username): ActionResult
    {
        $startedAt = $context->payloadValue('remote_mailbox_wait_started_at');
        if (! is_string($startedAt) || $startedAt === '') {
            $startedAt = now()->toIso8601String();
        }

        $timeoutHours = max(1, (int) config(
            'intranet-app-workflows.phase_b.remote_mailbox.timeout_hours',
            24,
        ));
        $pollMinutes = max(1, (int) config(
            'intranet-app-workflows.phase_b.remote_mailbox.poll_minutes',
            15,
        ));

        $started = Carbon::parse($startedAt);
        if ($started->copy()->addHours($timeoutHours)->isPast()) {
            return ActionResult::failed(
                message: "Timeout: Keine Cloud-Mailbox (x500) für [{$username}] innerhalb von {$timeoutHours}h",
                retryable: true,
                errors: ['ExchangeLabs x500 in proxyAddresses fehlt weiterhin'],
            );
        }

        $until = now()->addMinutes($pollMinutes);

        return ActionResult::waiting(
            message: "Warte auf Cloud-Mailbox (x500) für [{$username}] – nächster Check ".$until->format('d.m.Y H:i'),
            until: $until,
            output: ['remote_mailbox_wait_started_at' => $startedAt],
            messages: [
                'Lizenzgruppe gesetzt – Exchange Online provisioniert die Mailbox.',
                'Sobald x500:/o=ExchangeLabs… in proxyAddresses erscheint, wird der Remote-Mailbox-Stamp gesetzt.',
            ],
        );
    }
}
