<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class FlowAccess
{
    /**
     * Natürliche Bearbeitung (zugewiesener User / Initiator ohne Assignee).
     * Manager bekommen hier keinen Bypass — UI zeigt sonst sofort das Formular.
     */
    public static function canEdit(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        if (! $user || ! self::isOpen($flow)) {
            return false;
        }

        $userId = (int) $user->getAuthIdentifier();

        if ($flow->assignee_user_id !== null) {
            return (int) $flow->assignee_user_id === $userId;
        }

        // Gruppen-Pool ohne Claim: niemand bearbeitet direkt (erst „Mir zuweisen“).
        if (filled($flow->assignee_group_key)) {
            return false;
        }

        // Kein Assignee: Initiator darf den aktuellen Schritt machen.
        return (int) $flow->initiator_id === $userId;
    }

    /**
     * Manager-Notfall: Schritt bearbeiten dürfen, ohne Assignee zu sein.
     * UI muss explizit aktivieren — nicht automatisch Formular öffnen.
     */
    public static function canForceEdit(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        if (! $user || ! self::isOpen($flow) || ! self::isManager($user)) {
            return false;
        }

        return ! self::canEdit($flow, $user);
    }

    public static function canSubmit(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        return self::canEdit($flow, $user) || self::canForceEdit($flow, $user);
    }

    public static function canView(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::canBrowseAll($user)) {
            return true;
        }

        $userId = (int) $user->getAuthIdentifier();

        if ((int) $flow->initiator_id === $userId) {
            return true;
        }

        if ($flow->assignee_user_id !== null && (int) $flow->assignee_user_id === $userId) {
            return true;
        }

        if (filled($flow->assignee_group_key)) {
            return AssigneeGroups::userBelongsTo((string) $flow->assignee_group_key, $user);
        }

        return false;
    }

    public static function canClaim(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        if (! $user || ! self::isOpen($flow)) {
            return false;
        }

        if (! filled($flow->assignee_group_key) || $flow->assignee_user_id !== null) {
            return false;
        }

        if (self::isManager($user)) {
            return true;
        }

        return AssigneeGroups::userBelongsTo((string) $flow->assignee_group_key, $user);
    }

    /**
     * Admin-/HR-Übersicht: alle Workflows einsehen dürfen.
     */
    public static function canBrowseAll(?Authenticatable $user): bool
    {
        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        return $user->can('all-app-workflows')
            || $user->can('manage-app-workflows');
    }

    /**
     * Workflows starten (Hub + Create-Formulare).
     */
    public static function canCreate(?Authenticatable $user): bool
    {
        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        return $user->can('create-app-workflows')
            || $user->can('manage-app-workflows');
    }

    public static function canRelease(WorkflowFlow $flow, ?Authenticatable $user): bool
    {
        if (! $user || ! self::isOpen($flow)) {
            return false;
        }

        if (! filled($flow->assignee_group_key) || $flow->assignee_user_id === null) {
            return false;
        }

        if (self::isManager($user)) {
            return true;
        }

        return (int) $flow->assignee_user_id === (int) $user->getAuthIdentifier();
    }

    /**
     * @param  Builder<WorkflowFlow>  $query
     * @return Builder<WorkflowFlow>
     */
    public static function constrainVisibleTo(Builder $query, ?Authenticatable $user, bool $showAll = false): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($showAll && self::canBrowseAll($user)) {
            return $query;
        }

        $userId = (int) $user->getAuthIdentifier();
        $groupKeys = AssigneeGroups::keysForUser($user);

        return $query->where(function (Builder $inner) use ($userId, $groupKeys): void {
            $inner->where('initiator_id', $userId)
                ->orWhere('assignee_user_id', $userId);

            if ($groupKeys !== []) {
                $inner->orWhereIn('assignee_group_key', $groupKeys);
            }
        });
    }

    public static function assigneeLabel(WorkflowFlow $flow): string
    {
        if ($flow->assignee_user_id !== null) {
            return $flow->assignee?->name ?? ('User #'.$flow->assignee_user_id);
        }

        if (filled($flow->assignee_group_key)) {
            return 'Gruppe '.AssigneeGroups::label((string) $flow->assignee_group_key);
        }

        return '—';
    }

    private static function isOpen(WorkflowFlow $flow): bool
    {
        return $flow->status->isOpen();
    }

    private static function isManager(?Authenticatable $user): bool
    {
        return $user !== null
            && method_exists($user, 'can')
            && $user->can('manage-app-workflows');
    }
}
