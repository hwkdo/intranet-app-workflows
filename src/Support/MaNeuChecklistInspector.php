<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use App\Models\Standort;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;

/**
 * Auto-Prüfungen für ma_neu IT-Checkliste (AD, Mailbox, Proxy-Adressen).
 *
 * @phpstan-type ChecklistStatus array{
 *     username: string,
 *     ad_enabled: bool|null,
 *     has_mailbox: bool,
 *     proxy_ok: bool,
 *     expected_proxies: list<string>,
 *     actual_proxies: list<string>,
 *     missing_proxies: list<string>,
 *     phone_display: string,
 *     password: string,
 *     bitwarden_sent: bool,
 *     bitwarden_email: string,
 *     bitwarden_invited: bool,
 *     bitwarden_invite_email: string
 * }
 */
final class MaNeuChecklistInspector
{
    public function __construct(
        private readonly LdapIdentityGatewayInterface $ldap,
    ) {}

    /**
     * @return ChecklistStatus
     */
    public function inspect(WorkflowFlow $flow): array
    {
        $username = trim((string) $flow->getPayloadValue('username', ''));
        $password = (string) $flow->getPayloadValue('password', '');
        $bitwardenSent = (bool) $flow->getPayloadValue('supervisor_password_bitwarden_sent', false);
        $bitwardenEmail = trim((string) $flow->getPayloadValue('supervisor_password_email', ''));
        $bitwardenInvited = (bool) $flow->getPayloadValue('bitwarden_invited', false);
        $bitwardenInviteEmail = trim((string) $flow->getPayloadValue('bitwarden_invite_email', ''));

        $adEnabled = $username !== '' ? $this->ldap->isUserEnabled($username) : null;
        $state = $username !== '' ? $this->ldap->getRemoteMailboxState($username) : null;

        $actual = array_values($state['proxy_addresses'] ?? []);
        $mail = trim((string) ($state['mail'] ?? $flow->getPayloadValue('mail', '') ?? ''));
        $given = trim((string) ($state['given_name'] ?? $flow->getPayloadValue('vorname', '') ?? ''));
        $sn = trim((string) ($state['sn'] ?? $flow->getPayloadValue('nachname', '') ?? ''));

        $expected = [];
        if ($username !== '' && $mail !== '' && $given !== '' && $sn !== '') {
            $built = RemoteMailboxAttributes::build($username, $mail, $given, $sn, $actual);
            $expected = array_values(array_filter(
                $built['proxyaddresses'] ?? [],
                static fn (mixed $address): bool => is_string($address) && ! str_starts_with(strtolower($address), 'x500:'),
            ));
        }

        $missing = [];
        foreach ($expected as $address) {
            if (! $this->proxyListContains($actual, $address)) {
                $missing[] = $address;
            }
        }

        $hasMailbox = $state !== null && (
            RemoteMailboxAttributes::hasExchangeLabsX500($actual)
            || RemoteMailboxAttributes::looksEnabled([
                'mail_nickname' => $state['mail_nickname'] ?? null,
                'target_address' => $state['target_address'] ?? null,
                'recipient_type_details' => $state['recipient_type_details'] ?? null,
            ])
            || filled($state['target_address'] ?? null)
        );

        return [
            'username' => $username,
            'ad_enabled' => $adEnabled,
            'has_mailbox' => $hasMailbox,
            'proxy_ok' => $expected !== [] && $missing === [],
            'expected_proxies' => $expected,
            'actual_proxies' => $actual,
            'missing_proxies' => $missing,
            'phone_display' => $this->phoneDisplay($flow),
            'password' => $password,
            'bitwarden_sent' => $bitwardenSent,
            'bitwarden_email' => $bitwardenEmail,
            'bitwarden_invited' => $bitwardenInvited,
            'bitwarden_invite_email' => $bitwardenInviteEmail,
        ];
    }

    public function phoneDisplay(WorkflowFlow $flow): string
    {
        $extension = trim((string) $flow->getPayloadValue('telefon', ''));
        $standortId = $flow->getPayloadValue('standort');
        $prefix = '';

        if ($standortId !== null && $standortId !== '' && class_exists(Standort::class)) {
            $standort = Standort::query()->find($standortId);
            $prefix = trim((string) ($standort?->telefonnr ?? ''));
        }

        $full = trim($prefix.$extension);

        return $full !== '' ? $full : ($extension !== '' ? $extension : '—');
    }

    /**
     * @param  list<string>  $proxies
     */
    private function proxyListContains(array $proxies, string $needle): bool
    {
        $needleKey = strtolower($needle);

        foreach ($proxies as $proxy) {
            if (strtolower((string) $proxy) === $needleKey) {
                return true;
            }
        }

        return false;
    }
}
