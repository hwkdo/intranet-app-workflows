<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class WorkflowModels
{
    /**
     * @return class-string<Model>
     */
    public static function user(): string
    {
        return (string) config('intranet-app-workflows.user_model', User::class);
    }

    /**
     * @return Builder<Model>
     */
    public static function userQuery(): Builder
    {
        return static::user()::query();
    }

    /**
     * Aktive Intranet-User für durchsuchbare Selects (pro Request gecacht).
     *
     * @return EloquentCollection<int, Model>
     */
    public static function activeUsersForSelect(): EloquentCollection
    {
        return once(function (): EloquentCollection {
            $query = static::userQuery();

            if (method_exists(static::user(), 'scopeAktiv')) {
                $query->aktiv();
            } else {
                $query->where('active', true);
            }

            return $query
                ->orderBy('vorname')
                ->orderBy('nachname')
                ->get(['id', 'vorname', 'nachname']);
        });
    }
}
