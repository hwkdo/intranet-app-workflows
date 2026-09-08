<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use App\Support\StandortCiscoDefaults;

/**
 * Remap von Outbound-CSS-Namen auf neues Standort-Kürzel.
 * SUBSCRIBE_CSS und leere Werte werden übersprungen; unbekannte Formate sind Fehler.
 */
final class CiscoOutboundCssRemapper
{
    /**
     * @return array{status: 'skip'|'ok'|'error', value: ?string, message: ?string}
     */
    public static function remap(string $cssName, string $toSiteCode): array
    {
        $cssName = trim($cssName);
        if ($cssName === '') {
            return ['status' => 'skip', 'value' => null, 'message' => null];
        }

        if (strcasecmp($cssName, 'SUBSCRIBE_CSS') === 0) {
            return ['status' => 'skip', 'value' => null, 'message' => 'SUBSCRIBE_CSS unverändert'];
        }

        if (! class_exists(StandortCiscoDefaults::class)) {
            return [
                'status' => 'error',
                'value' => null,
                'message' => 'StandortCiscoDefaults nicht verfügbar',
            ];
        }

        $level = StandortCiscoDefaults::levelFromOutboundCss($cssName);
        if ($level === null) {
            return [
                'status' => 'error',
                'value' => null,
                'message' => "CSS [{$cssName}] ist nicht remappbar",
            ];
        }

        return [
            'status' => 'ok',
            'value' => StandortCiscoDefaults::outboundCss($toSiteCode, $level),
            'message' => null,
        ];
    }
}
