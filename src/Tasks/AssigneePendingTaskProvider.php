<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

final class AssigneePendingTaskProvider implements TaskProviderInterface
{
    public function getLabel(): string
    {
        return 'Workflows zur Bearbeitung';
    }

    /**
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        $userId = (int) $user->getAuthIdentifier();
        if ($userId < 1) {
            return collect();
        }

        return WorkflowFlow::query()
            ->with(['type.steps'])
            ->where('status', FlowStatus::Active)
            ->where('assignee_user_id', $userId)
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (WorkflowFlow $flow): TaskItem {
                $step = $flow->type?->steps
                    ?->firstWhere('position', $flow->current_step_position);

                $descriptionParts = array_filter([
                    $step?->title ? 'Schritt: '.$step->title : null,
                    $flow->due_date ? 'Stichtag '.$flow->due_date->format('d.m.Y') : null,
                ]);

                return new TaskItem(
                    title: FlowTitle::for($flow),
                    url: route('apps.workflows.flows.show', $flow),
                    appIdentifier: IntranetAppWorkflows::identifier(),
                    appName: IntranetAppWorkflows::app_name(),
                    appIcon: IntranetAppWorkflows::app_icon(),
                    description: $descriptionParts !== [] ? implode(' · ', $descriptionParts) : null,
                    badge: $step?->title ?? 'Offen',
                    priority: 55,
                );
            })
            ->values();
    }
}
