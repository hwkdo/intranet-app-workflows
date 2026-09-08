<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Notifications\WorkflowAssignedGroupNotification;
use Hwkdo\IntranetAppWorkflows\Notifications\WorkflowAssignedPersonalNotification;
use Hwkdo\IntranetAppWorkflows\Support\AssigneeGroups;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class AssignmentNotifier
{
    public function notifyForCurrentAssignee(WorkflowFlow $flow): void
    {
        $flow->refresh();
        $flow->loadMissing(['type.steps']);

        try {
            if ($flow->assignee_user_id !== null) {
                $this->notifyPersonal($flow);

                return;
            }

            if (filled($flow->assignee_group_key)) {
                $this->notifyGroup($flow);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function notifyPersonal(WorkflowFlow $flow): void
    {
        $assigneeId = (int) $flow->assignee_user_id;

        // Fallback Initiator → keine Selbst-Benachrichtigung
        if ($assigneeId === (int) $flow->initiator_id) {
            return;
        }

        $user = WorkflowModels::userQuery()->find($assigneeId);
        if (! $user) {
            return;
        }

        $user->notify(new WorkflowAssignedPersonalNotification($flow));
    }

    private function notifyGroup(WorkflowFlow $flow): void
    {
        $groupKey = (string) $flow->assignee_group_key;
        $role = AssigneeGroups::roleName($groupKey);
        if ($role === null) {
            return;
        }

        $users = WorkflowModels::userQuery()
            ->role($role)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new WorkflowAssignedGroupNotification($flow));
    }
}
