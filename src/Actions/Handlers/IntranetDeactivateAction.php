<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

/**
 * Stichtag: Intranet-User zuerst deaktivieren, danach GVP-Felder leeren.
 */
final class IntranetDeactivateAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.intranet_deactivate';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $user) {
            return ActionResult::failed('Mitarbeiter nicht gefunden', retryable: false);
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Intranet-User #{$user->id} deaktivieren + GVP leeren",
            output: ['intranet_deactivate_user_id' => $user->id],
        )) {
            return $dry;
        }

        try {
            $user->active = false;
            $user->save();
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Intranet active=false fehlgeschlagen: '.$e->getMessage());
        }

        $messages = ['active → false'];
        $errors = [];

        try {
            $user->gvp_id = null;
            $user->save();
            $messages[] = 'gvp_id → null';
        } catch (Throwable $e) {
            report($e);
            $errors[] = 'gvp_id: '.$e->getMessage();
        }

        if (method_exists($user, 'gvpSecondary')) {
            try {
                $user->gvpSecondary()->sync([]);
                $messages[] = 'gvpSecondary geleert';
            } catch (Throwable $e) {
                report($e);
                $errors[] = 'gvpSecondary: '.$e->getMessage();
            }
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Intranet-User deaktiviert, GVP-Cleanup teilweise fehlgeschlagen',
                messages: $messages,
                errors: $errors,
                output: ['intranet_deactivated' => true, 'intranet_deactivate_user_id' => $user->id],
            );
        }

        return ActionResult::succeeded(
            message: "Intranet-User #{$user->id} deaktiviert",
            messages: $messages,
            output: ['intranet_deactivated' => true, 'intranet_deactivate_user_id' => $user->id],
        );
    }
}
