<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class FlowIndexQuery
{
    /**
     * @param  Builder<WorkflowFlow>  $query
     * @return Builder<WorkflowFlow>
     */
    public static function apply(
        Builder $query,
        ?Authenticatable $user,
        bool $showAll,
        bool $showCompleted,
        string $search = '',
    ): Builder {
        $query = FlowAccess::constrainVisibleTo($query, $user, $showAll);

        if (! $showCompleted) {
            $query->where('status', '!=', FlowStatus::Completed);
        }

        $term = trim($search);
        if ($term !== '') {
            self::constrainSearch($query, $term);
        }

        return $query;
    }

    /**
     * @param  Builder<WorkflowFlow>  $query
     */
    private static function constrainSearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $matchingUserIds = WorkflowModels::userQuery()
            ->where(function (Builder $inner) use ($like): void {
                $inner->where('vorname', 'like', $like)
                    ->orWhere('nachname', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->limit(200)
            ->pluck('id')
            ->all();

        $query->where(function (Builder $inner) use ($like, $matchingUserIds): void {
            $inner->where('payload->vorname', 'like', $like)
                ->orWhere('payload->nachname', 'like', $like)
                ->orWhere('payload->username', 'like', $like)
                ->orWhere('payload->mitarbeiter_name', 'like', $like)
                ->orWhereHas('type', function (Builder $typeQuery) use ($like): void {
                    $typeQuery->where('title', 'like', $like);
                });

            if ($matchingUserIds !== []) {
                $inner->orWhereIn('payload->mitarbeiter', $matchingUserIds);
            }
        });
    }
}
