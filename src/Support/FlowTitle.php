<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;

final class FlowTitle
{
    public static function for(WorkflowFlow $flow): string
    {
        $typeTitle = $flow->type?->title ?? 'Workflow';
        $name = self::employeeName($flow);

        return $name !== '' ? "{$typeTitle}: {$name}" : $typeTitle;
    }

    public static function employeeName(WorkflowFlow $flow): string
    {
        $fromPayload = trim((string) $flow->getPayloadValue('mitarbeiter_name', ''));
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        $vorname = trim((string) $flow->getPayloadValue('vorname', ''));
        $nachname = trim((string) $flow->getPayloadValue('nachname', ''));
        $name = trim("{$vorname} {$nachname}");

        if ($name !== '') {
            return $name;
        }

        $mitarbeiterId = $flow->getPayloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return '';
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $user) {
            return '';
        }

        $resolved = trim((string) ($user->name ?? (trim(($user->vorname ?? '').' '.($user->nachname ?? '')))));
        if ($resolved !== '') {
            return $resolved;
        }

        return trim((string) ($user->username ?? ''));
    }
}
