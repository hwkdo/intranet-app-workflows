<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Services\AssignmentNotifier;
use Hwkdo\IntranetAppWorkflows\Support\GvpSupervisorResolver;

/**
 * Sync assignee resolution — always last in the step action sequence.
 *
 * Config (step_action.config_override):
 * - group: fester Gruppen-Key (z. B. "it")
 * - user_id: fester User
 * - payload_user_key / payload_group_key: Keys im Flow-Payload
 * - gvp_payload_key: z. B. "abteilung" → GVP-Vorgesetzter (Legacy vorgesetzter_abteilung)
 * - gvp_from_mitarbeiter: true → Vorgesetzter aus gvp_id des Payload-Mitarbeiters
 * - fallback_to_initiator: bool (Default true)
 */
final class ResolveAssigneeAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly AssignmentNotifier $assignmentNotifier,
    ) {}

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

        $gvpPayloadKey = $context->config['gvp_payload_key'] ?? null;
        if (($userId === null || $userId === '') && is_string($gvpPayloadKey) && $gvpPayloadKey !== '') {
            $userId = GvpSupervisorResolver::userIdForAbteilung(
                $context->payloadValue($gvpPayloadKey),
            );
        }

        if (($userId === null || $userId === '') && ! empty($context->config['gvp_from_mitarbeiter'])) {
            $mitarbeiterId = $context->payloadValue('mitarbeiter');
            if (is_numeric($mitarbeiterId)) {
                $mitarbeiter = \Hwkdo\IntranetAppWorkflows\Support\WorkflowModels::userQuery()->find((int) $mitarbeiterId);
                $gvpId = $mitarbeiter?->gvp_id ?? null;
                if ($gvpId !== null && $gvpId !== '') {
                    $userId = GvpSupervisorResolver::userIdForAbteilung($gvpId);
                }
            }
        }

        if ($userId !== null && $userId !== '' && $group !== null && $group !== '') {
            // Group wins (legacy behaviour), still report both.
            $result = $this->assignGroup($context, (string) $group, "Assignee group [{$group}] gesetzt (User-Kandidat ignoriert)");
            $this->assignmentNotifier->notifyForCurrentAssignee($context->flow);

            return $result;
        }

        if ($userId !== null && $userId !== '') {
            $result = $this->assignUser($context, (int) $userId, "Assignee user [{$userId}] gesetzt");
            $this->assignmentNotifier->notifyForCurrentAssignee($context->flow);

            return $result;
        }

        if ($group !== null && $group !== '') {
            $result = $this->assignGroup($context, (string) $group, "Assignee group [{$group}] gesetzt");
            $this->assignmentNotifier->notifyForCurrentAssignee($context->flow);

            return $result;
        }

        $fallbackToInitiator = (bool) ($context->config['fallback_to_initiator'] ?? true);
        if ($fallbackToInitiator && $context->flow->initiator_id) {
            $result = $this->assignUser(
                $context,
                (int) $context->flow->initiator_id,
                'Kein Assignee ermittelt – Fallback Initiator',
            );
            $this->assignmentNotifier->notifyForCurrentAssignee($context->flow);

            return $result;
        }

        $context->flow->forceFill([
            'assignee_user_id' => null,
            'assignee_group_key' => null,
        ])->save();

        return ActionResult::succeeded('Kein Assignee ermittelt');
    }

    private function assignUser(ActionContext $context, int $userId, string $message): ActionResult
    {
        $context->flow->forceFill([
            'assignee_user_id' => $userId,
            'assignee_group_key' => null,
        ])->save();

        return ActionResult::succeeded(
            message: $message,
            output: ['assignee_user_id' => $userId],
        );
    }

    private function assignGroup(ActionContext $context, string $group, string $message): ActionResult
    {
        $context->flow->forceFill([
            'assignee_user_id' => null,
            'assignee_group_key' => $group,
        ])->save();

        return ActionResult::succeeded(
            message: $message,
            output: ['assignee_group_key' => $group],
        );
    }
}
