<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

/**
 * Baut die On-Prem-LDAP-Attribute für Hybrid Remote User Mailbox
 * (entspricht Easy365Manager / Enable-RemoteMailbox).
 */
final class RemoteMailboxAttributes
{
    /**
     * @param  list<string>  $existingProxyAddresses
     * @return array<string, mixed>
     */
    public static function build(
        string $username,
        string $mail,
        string $givenName,
        string $sn,
        array $existingProxyAddresses = [],
    ): array {
        $username = mb_strtolower(trim($username));
        $mail = mb_strtolower(trim($mail));
        $givenName = trim($givenName);
        $sn = trim($sn);

        $routingDomain = mb_strtolower((string) config(
            'intranet-app-workflows.phase_b.remote_mailbox.routing_domain',
            'hwkdoedu.mail.onmicrosoft.com',
        ));
        $onmicrosoftDomain = mb_strtolower((string) config(
            'intranet-app-workflows.phase_b.remote_mailbox.onmicrosoft_domain',
            'hwkdoedu.onmicrosoft.com',
        ));
        $primaryDomain = mb_strtolower((string) config(
            'intranet-app-workflows.phase_b.remote_mailbox.primary_mail_domain',
            'hwk-do.de',
        ));
        /** @var list<string> $samDomains */
        $samDomains = array_values(array_filter(array_map(
            static fn (mixed $domain): string => mb_strtolower(trim((string) $domain)),
            (array) config('intranet-app-workflows.phase_b.remote_mailbox.proxy_sam_domains', [
                'hwk-do.de',
                'hwkdo.de',
                'verwaltung.hwkdo.de',
            ]),
        )));

        $shortAliasLocal = mb_strtolower(self::shortAliasLocalPart($givenName, $sn));

        $desired = [];
        if ($mail !== '') {
            $desired[] = 'SMTP:'.$mail;
        }
        if ($shortAliasLocal !== '') {
            $desired[] = 'smtp:'.$shortAliasLocal.'@'.$primaryDomain;
        }
        foreach ($samDomains as $domain) {
            if ($domain === '') {
                continue;
            }
            $desired[] = 'smtp:'.$username.'@'.$domain;
        }
        $desired[] = 'smtp:'.$username.'@'.$routingDomain;
        $desired[] = 'smtp:'.$username.'@'.$onmicrosoftDomain;

        $proxies = self::mergeProxyAddresses($existingProxyAddresses, $desired);

        $attributes = [
            'mailnickname' => $username,
            'targetaddress' => 'smtp:'.$username.'@'.$routingDomain,
            'msexchrecipientdisplaytype' => (string) config(
                'intranet-app-workflows.phase_b.remote_mailbox.ms_exch_recipient_display_type',
                '-2147483642',
            ),
            'msexchrecipienttypedetails' => (string) config(
                'intranet-app-workflows.phase_b.remote_mailbox.ms_exch_recipient_type_details',
                '2147483648',
            ),
            'msexchremoterecipienttype' => (string) config(
                'intranet-app-workflows.phase_b.remote_mailbox.ms_exch_remote_recipient_type',
                '4',
            ),
            'msexchversion' => (string) config(
                'intranet-app-workflows.phase_b.remote_mailbox.ms_exch_version',
                '44220983382016',
            ),
            'proxyaddresses' => $proxies,
        ];

        if ($mail !== '') {
            $attributes['mail'] = $mail;
        }

        return $attributes;
    }

    public static function shortAliasLocalPart(string $givenName, string $sn): string
    {
        $givenName = trim($givenName);
        $sn = trim($sn);
        if ($givenName === '' || $sn === '') {
            return '';
        }

        return mb_substr($givenName, 0, 1).$sn;
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $desired
     * @return list<string>
     */
    public static function mergeProxyAddresses(array $existing, array $desired): array
    {
        $x500 = [];
        foreach ($existing as $address) {
            if (! is_string($address) || $address === '') {
                continue;
            }
            if (str_starts_with(strtolower($address), 'x500:')) {
                $x500[] = $address;
            }
        }

        $merged = [];
        $seen = [];
        foreach (array_merge($desired, $x500) as $address) {
            $key = strtolower($address);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $address;
        }

        return $merged;
    }

    public static function hasExchangeLabsX500(array $proxyAddresses): bool
    {
        foreach ($proxyAddresses as $address) {
            if (! is_string($address)) {
                continue;
            }
            if (str_contains(strtolower($address), 'x500:/o=exchangelabs')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{mail_nickname?: ?string, target_address?: ?string, recipient_type_details?: ?string}  $state
     */
    public static function looksEnabled(array $state): bool
    {
        $expectedDetails = (string) config(
            'intranet-app-workflows.phase_b.remote_mailbox.ms_exch_recipient_type_details',
            '2147483648',
        );

        return trim((string) ($state['mail_nickname'] ?? '')) !== ''
            && trim((string) ($state['target_address'] ?? '')) !== ''
            && (string) ($state['recipient_type_details'] ?? '') === $expectedDetails;
    }
}
