<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

/**
 * Nach HR-Schritt: username, Name und alte Abteilung aus dem gewählten Mitarbeiter ableiten.
 */
final class CaptureMitarbeiterMetaAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_umsetzung.capture_mitarbeiter_meta';
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

        $username = trim((string) ($user->username ?? ''));
        if ($username === '') {
            return ActionResult::failed('Mitarbeiter hat keinen AD-Username', retryable: false);
        }

        $vorname = (string) ($user->vorname ?? '');
        $nachname = (string) ($user->nachname ?? '');
        $mitarbeiterName = trim($vorname.' '.$nachname);
        if ($mitarbeiterName === '') {
            $mitarbeiterName = $username;
        }

        $output = [
            'username' => $username,
            'vorname' => $vorname,
            'nachname' => $nachname,
            'mitarbeiter_name' => $mitarbeiterName,
        ];

        $existingOld = $context->payloadValue('abteilung_old');
        if ($existingOld === null || $existingOld === '') {
            $output['abteilung_old'] = $user->gvp_id;
        }

        return ActionResult::succeeded(
            message: "Mitarbeiter-Meta erfasst ({$username})",
            output: $output,
        );
    }
}
