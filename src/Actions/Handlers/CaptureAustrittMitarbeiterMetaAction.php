<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

/**
 * Step 1 Austritt: Name/Username im Payload speichern (bleibt nach Deaktivierung sichtbar/filterbar).
 */
final class CaptureAustrittMitarbeiterMetaAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.capture_mitarbeiter_meta';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $user) {
            return ActionResult::failed('Mitarbeiter nicht gefunden', retryable: false);
        }

        $vorname = trim((string) ($user->vorname ?? ''));
        $nachname = trim((string) ($user->nachname ?? ''));
        $username = trim((string) ($user->username ?? ''));
        $mitarbeiterName = trim($vorname.' '.$nachname);

        if ($mitarbeiterName === '') {
            $mitarbeiterName = $username !== '' ? $username : ('User #'.$user->id);
        }

        return ActionResult::succeeded(
            message: "Mitarbeiter-Meta erfasst ({$mitarbeiterName})",
            output: [
                'username' => $username,
                'vorname' => $vorname,
                'nachname' => $nachname,
                'mitarbeiter_name' => $mitarbeiterName,
            ],
        );
    }
}
