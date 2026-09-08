<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Step 2: Vorgesetzten-User-ID im Payload speichern (für habe_ich_erhalten / spätere Dispositionen).
 */
final class CaptureAustrittSupervisorMetaAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.capture_supervisor_meta';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $existing = $context->payloadValue('vorgesetzter_user_id');
        if (is_numeric($existing) && (int) $existing > 0) {
            return ActionResult::succeeded(
                message: 'Vorgesetzter bereits im Payload',
                output: ['vorgesetzter_user_id' => (int) $existing],
            );
        }

        $userId = null;
        $assigneeId = $context->flow->assignee_user_id ?? null;
        if (is_numeric($assigneeId) && (int) $assigneeId > 0) {
            $userId = (int) $assigneeId;
        }

        if ($userId === null) {
            $authId = Auth::id();
            if (is_numeric($authId) && (int) $authId > 0) {
                $userId = (int) $authId;
            }
        }

        if ($userId === null) {
            return ActionResult::failed(
                'Vorgesetzter konnte nicht ermittelt werden (kein assignee / Auth)',
                retryable: false,
            );
        }

        return ActionResult::succeeded(
            message: "Vorgesetzter #{$userId} erfasst",
            output: [
                'vorgesetzter_user_id' => $userId,
                'step2_actor_user_id' => $userId,
            ],
        );
    }
}
