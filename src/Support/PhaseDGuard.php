<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;

/**
 * Safety-Gate für Phase-D-Actions (Onboarding-/Initiator-Mails).
 */
final class PhaseDGuard
{
    public static function enabled(): bool
    {
        return (bool) config('intranet-app-workflows.phase_d.enabled', false);
    }

    public static function dryRun(): bool
    {
        return (bool) config('intranet-app-workflows.phase_d.dry_run', true);
    }

    /**
     * @return ActionResult|null Early-exit (skipped), sonst null = weiterlaufen
     */
    public static function preflight(): ?ActionResult
    {
        if (! self::enabled()) {
            return ActionResult::skipped('Phase D deaktiviert (intranet-app-workflows.phase_d.enabled=false)');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>  $messages
     */
    public static function assertNotDryRunOrMessage(string $actionLabel, array $output = [], array $messages = []): ?ActionResult
    {
        if (! self::dryRun()) {
            return null;
        }

        $defaultMessages = ["[Dry-Run] {$actionLabel} – kein Mailversand"];

        return ActionResult::succeeded(
            message: "[Dry-Run] {$actionLabel}",
            output: array_merge(['phase_d_dry_run' => true], $output),
            messages: $messages !== [] ? array_merge($defaultMessages, $messages) : $defaultMessages,
        );
    }
}
