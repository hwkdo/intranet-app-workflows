<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;

/**
 * Katalog der Workflow-Empfängergruppen (Legacy: GroupReceiver / Workflows-*).
 *
 * Keys landen in flows.assignee_group_key; Membership läuft über Spatie-Rollen.
 */
final class AssigneeGroups
{
    /**
     * @return array<string, array{role: string, label: string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{role?: string, label?: string}> $groups */
        $groups = config('intranet-app-workflows.assignee_groups', []);

        $normalized = [];
        foreach ($groups as $key => $meta) {
            $role = (string) ($meta['role'] ?? '');
            if ($role === '') {
                continue;
            }
            $normalized[(string) $key] = [
                'role' => $role,
                'label' => (string) ($meta['label'] ?? $key),
            ];
        }

        return $normalized;
    }

    public static function roleName(string $key): ?string
    {
        return self::all()[$key]['role'] ?? null;
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    public static function userBelongsTo(string $key, ?Authenticatable $user): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        $role = self::roleName($key);
        if ($role === null) {
            return false;
        }

        return (bool) $user->hasRole($role);
    }

    /**
     * @return list<string>
     */
    public static function keysForUser(?Authenticatable $user): array
    {
        if (! $user) {
            return [];
        }

        $keys = [];
        foreach (array_keys(self::all()) as $key) {
            if (self::userBelongsTo($key, $user)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Stellt sicher, dass die konfigurierten Spatie-Rollen existieren (Tests / Sync).
     */
    public static function ensureRolesExist(): void
    {
        foreach (self::all() as $meta) {
            Role::findOrCreate($meta['role'], 'web');
        }
    }
}
