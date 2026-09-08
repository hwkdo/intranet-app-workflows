<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;

/**
 * Safety-Gate für Phase-C-Actions (Tickets / Intranet-Rollen / BuE-Rollen).
 */
final class PhaseCGuard
{
    public static function enabled(): bool
    {
        return (bool) config('intranet-app-workflows.phase_c.enabled', false);
    }

    public static function dryRun(): bool
    {
        return (bool) config('intranet-app-workflows.phase_c.dry_run', true);
    }

    /**
     * @return ActionResult|null Early-exit (skipped), sonst null = weiterlaufen
     */
    public static function preflight(): ?ActionResult
    {
        if (! self::enabled()) {
            return ActionResult::skipped('Phase C deaktiviert (intranet-app-workflows.phase_c.enabled=false)');
        }

        return null;
    }

    public static function assertNotDryRunOrMessage(string $actionLabel, array $output = [], array $messages = []): ?ActionResult
    {
        if (! self::dryRun()) {
            return null;
        }

        $defaultMessages = ["[Dry-Run] {$actionLabel} – kein Schreibzugriff / kein Mailversand"];

        return ActionResult::succeeded(
            message: "[Dry-Run] {$actionLabel}",
            output: array_merge(['phase_c_dry_run' => true], $output),
            messages: $messages !== [] ? array_merge($defaultMessages, $messages) : $defaultMessages,
        );
    }
}
