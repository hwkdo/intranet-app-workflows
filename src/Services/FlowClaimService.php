<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Enums\HistoryWhat;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowHistory;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FlowClaimService
{
    public function claim(WorkflowFlow $flow, Authenticatable $user): WorkflowFlow
    {
        if (! FlowAccess::canClaim($flow, $user)) {
            throw new RuntimeException('Flow kann nicht zugewiesen werden.');
        }

        return DB::transaction(function () use ($flow, $user): WorkflowFlow {
            $locked = WorkflowFlow::query()->lockForUpdate()->findOrFail($flow->id);

            if (! FlowAccess::canClaim($locked, $user)) {
                throw new RuntimeException('Flow wurde zwischenzeitlich bereits zugewiesen.');
            }

            $userId = (int) $user->getAuthIdentifier();
            $locked->forceFill([
                'assignee_user_id' => $userId,
            ])->save();

            WorkflowHistory::query()->create([
                'flow_id' => $locked->id,
                'step_id' => $locked->currentStep()?->id,
                'user_id' => $userId,
                'what' => HistoryWhat::Claimed,
                'data' => [
                    'assignee_group_key' => $locked->assignee_group_key,
                    'assignee_user_id' => $userId,
                ],
            ]);

            return $locked->fresh(['assignee', 'initiator', 'type']);
        });
    }

    public function release(WorkflowFlow $flow, Authenticatable $user): WorkflowFlow
    {
        if (! FlowAccess::canRelease($flow, $user)) {
            throw new RuntimeException('Zuweisung kann nicht gelöscht werden.');
        }

        return DB::transaction(function () use ($flow, $user): WorkflowFlow {
            $locked = WorkflowFlow::query()->lockForUpdate()->findOrFail($flow->id);

            if (! FlowAccess::canRelease($locked, $user)) {
                throw new RuntimeException('Zuweisung kann nicht gelöscht werden.');
            }

            $previousUserId = $locked->assignee_user_id;
            $locked->forceFill([
                'assignee_user_id' => null,
            ])->save();

            WorkflowHistory::query()->create([
                'flow_id' => $locked->id,
                'step_id' => $locked->currentStep()?->id,
                'user_id' => (int) $user->getAuthIdentifier(),
                'what' => HistoryWhat::Released,
                'data' => [
                    'assignee_group_key' => $locked->assignee_group_key,
                    'previous_assignee_user_id' => $previousUserId,
                ],
            ]);

            return $locked->fresh(['assignee', 'initiator', 'type']);
        });
    }
}
