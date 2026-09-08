<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Baut den Onboarding-Dokumenten-Link (Legacy: mitarbeiter.onboarding).
 *
 * Prefer Route-Name wenn registriert, sonst URL-Template mit {id}/{username}.
 */
final class OnboardingLinkBuilder
{
    public static function forUser(Model $user): string
    {
        $routeName = trim((string) config('intranet-app-workflows.phase_d.onboarding_route', ''));
        if ($routeName !== '' && Route::has($routeName)) {
            return route($routeName, $user);
        }

        $template = trim((string) config('intranet-app-workflows.phase_d.onboarding_url_template', ''));
        if ($template !== '') {
            return str_replace(
                ['{id}', '{username}'],
                [(string) $user->getKey(), (string) ($user->username ?? '')],
                $template,
            );
        }

        throw new RuntimeException(
            'Onboarding-Link nicht konfiguriert: phase_d.onboarding_route oder phase_d.onboarding_url_template setzen.',
        );
    }
}
