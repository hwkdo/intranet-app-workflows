<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use RuntimeException;

/**
 * Safety-Gate für Phase-B-Identity-Actions (AD anlegen / aktivieren / importieren).
 */
final class PhaseBGuard
{
    public static function enabled(): bool
    {
        return (bool) config('intranet-app-workflows.phase_b.enabled', false);
    }

    public static function dryRun(): bool
    {
        return (bool) config('intranet-app-workflows.phase_b.dry_run', true);
    }

    public static function usernamePrefix(): string
    {
        return (string) config('intranet-app-workflows.phase_b.require_username_prefix', '');
    }

    public static function upnSuffix(): string
    {
        $suffix = (string) config('intranet-app-workflows.phase_b.upn_suffix', '@hwk-do.de');

        return str_starts_with($suffix, '@') ? $suffix : '@'.$suffix;
    }

    public static function homeshareLetter(): string
    {
        return (string) config('intranet-app-workflows.phase_b.homeshare_letter', 'P');
    }

    /**
     * @return ActionResult|null  Early-exit Result (skipped / failed), sonst null = weiterlaufen
     */
    public static function preflight(?string $username): ?ActionResult
    {
        if (! self::enabled()) {
            return ActionResult::skipped('Phase B deaktiviert (intranet-app-workflows.phase_b.enabled=false)');
        }

        $username = trim((string) $username);
        if ($username === '') {
            return ActionResult::failed('Username fehlt im Payload', retryable: false);
        }

        $prefix = self::usernamePrefix();
        if ($prefix !== '' && ! str_starts_with(strtolower($username), strtolower($prefix))) {
            return ActionResult::failed(
                message: "Username [{$username}] verletzt Safety-Prefix [{$prefix}]",
                retryable: false,
            );
        }

        return null;
    }

    public static function assertNotDryRunOrMessage(string $actionLabel): ?ActionResult
    {
        if (! self::dryRun()) {
            return null;
        }

        return ActionResult::succeeded(
            message: "[Dry-Run] {$actionLabel}",
            output: ['phase_b_dry_run' => true],
            messages: ["[Dry-Run] {$actionLabel} – kein LDAP-Schreibzugriff"],
        );
    }

    public static function requireUsername(mixed $username): string
    {
        $value = trim((string) $username);
        if ($value === '') {
            throw new RuntimeException('Username fehlt im Payload');
        }

        return $value;
    }
}
