<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;

/**
 * Sync assignee resolution — always last in the step action sequence.
 *
 * Config (step_action.config_override):
 * - group: fester Gruppen-Key (z. B. "it")
 * - user_id: fester User
 * - payload_user_key / payload_group_key: Keys im Flow-Payload
 * - fallback_to_initiator: bool (Default true)
 */
final class ResolveAssigneeAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'core.resolve_assignee';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $userKey = (string) ($context->config['payload_user_key'] ?? 'assignee_user_id');
        $groupKey = (string) ($context->config['payload_group_key'] ?? 'assignee_group_key');

        $userId = $context->config['user_id'] ?? $context->payloadValue($userKey);
        $group = $context->config['group'] ?? $context->payloadValue($groupKey);

        if ($userId !== null && $userId !== '' && $group !== null && $group !== '') {
            // Group wins (legacy behaviour), still report both.
            $context->flow->forceFill([
                'assignee_user_id' => null,
                'assignee_group_key' => (string) $group,
            ])->save();

            return ActionResult::succeeded(
                message: "Assignee group [{$group}] gesetzt (User-Kandidat ignoriert)",
                output: [
                    'assignee_group_key' => (string) $group,
                ],
            );
        }

        if ($userId !== null && $userId !== '') {
            $context->flow->forceFill([
                'assignee_user_id' => (int) $userId,
                'assignee_group_key' => null,
            ])->save();

            return ActionResult::succeeded(
                message: "Assignee user [{$userId}] gesetzt",
                output: ['assignee_user_id' => (int) $userId],
            );
        }

        if ($group !== null && $group !== '') {
            $context->flow->forceFill([
                'assignee_user_id' => null,
                'assignee_group_key' => (string) $group,
            ])->save();

            return ActionResult::succeeded(
                message: "Assignee group [{$group}] gesetzt",
                output: ['assignee_group_key' => (string) $group],
            );
        }

        // Phase-A / Dev-Fallback: Initiator behält den Ball, damit man den Flow allein durchspielen kann.
        $fallbackToInitiator = (bool) ($context->config['fallback_to_initiator'] ?? true);
        if ($fallbackToInitiator && $context->flow->initiator_id) {
            $context->flow->forceFill([
                'assignee_user_id' => (int) $context->flow->initiator_id,
                'assignee_group_key' => null,
            ])->save();

            return ActionResult::succeeded(
                message: 'Kein Assignee im Payload – Fallback Initiator',
                output: ['assignee_user_id' => (int) $context->flow->initiator_id],
            );
        }

        $context->flow->forceFill([
            'assignee_user_id' => null,
            'assignee_group_key' => null,
        ])->save();

        return ActionResult::succeeded('Kein Assignee ermittelt');
    }
}
