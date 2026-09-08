<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use App\Models\Gvp;

/**
 * Legacy-Parität: Gvp::vorgesetzter_abteilung.
 * Bei G/FB gilt der Vorgesetzte der Parent-GVP, sonst der der eigenen GVP.
 */
final class GvpSupervisorResolver
{
    public static function userIdForAbteilung(mixed $abteilungId): ?int
    {
        if ($abteilungId === null || $abteilungId === '') {
            return null;
        }

        $gvp = Gvp::query()->with(['parent', 'vorgesetzter'])->find($abteilungId);
        if (! $gvp instanceof Gvp) {
            return null;
        }

        $kuerzel = (string) ($gvp->kuerzel ?? '');
        if (in_array($kuerzel, ['G', 'FB'], true)) {
            $parent = $gvp->parent;
            $supervisorId = $parent?->vorgesetzter_id;

            return $supervisorId ? (int) $supervisorId : null;
        }

        return $gvp->vorgesetzter_id ? (int) $gvp->vorgesetzter_id : null;
    }
}
