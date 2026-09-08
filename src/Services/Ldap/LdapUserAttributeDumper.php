<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Ldap;

use App\Services\LdapRecordUserService;
use InvalidArgumentException;
use LdapRecord\Models\ActiveDirectory\User as ActiveDirectoryUser;
use RuntimeException;

final class LdapUserAttributeDumper
{
    /**
     * Attribute die nie in Dumps landen (Secrets / nutzlos für Diff).
     *
     * @var list<string>
     */
    private const REDACTED_ATTRIBUTES = [
        'unicodepwd',
        'userpassword',
        'ntpwdhistory',
        'lmpwdhistory',
        'supplementalcredentials',
    ];

    /**
     * @return array{
     *     meta: array{username: string, dn: string|null, dumped_at: string, attribute_count: int, group_count: int},
     *     attributes: array<string, mixed>,
     *     groups: list<string>
     * }
     */
    public function dump(string $username): array
    {
        $user = $this->findUser($username);

        // Alle User-Attribute inkl. operational attributes (+).
        $fresh = ActiveDirectoryUser::query()
            ->select(['*', '+'])
            ->findOrFail($user->getDn());

        $attributes = $this->normalizeAttributes($fresh->getAttributes());
        $groups = $this->groupNames($fresh);

        ksort($attributes);

        return [
            'meta' => [
                'username' => $username,
                'dn' => $fresh->getDn(),
                'dumped_at' => now()->toIso8601String(),
                'attribute_count' => count($attributes),
                'group_count' => count($groups),
            ],
            'attributes' => $attributes,
            'groups' => $groups,
        ];
    }

    /**
     * @param  array{attributes?: array<string, mixed>, groups?: list<string>}  $before
     * @param  array{attributes?: array<string, mixed>, groups?: list<string>}  $after
     * @return array{
     *     attributes: array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{before: mixed, after: mixed}>},
     *     groups: array{added: list<string>, removed: list<string>}
     * }
     */
    public function diff(array $before, array $after): array
    {
        $beforeAttrs = $before['attributes'] ?? [];
        $afterAttrs = $after['attributes'] ?? [];

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterAttrs as $key => $value) {
            if (! array_key_exists($key, $beforeAttrs)) {
                $added[$key] = $value;

                continue;
            }

            if ($this->valuesDiffer($beforeAttrs[$key], $value)) {
                $changed[$key] = [
                    'before' => $beforeAttrs[$key],
                    'after' => $value,
                ];
            }
        }

        foreach ($beforeAttrs as $key => $value) {
            if (! array_key_exists($key, $afterAttrs)) {
                $removed[$key] = $value;
            }
        }

        ksort($added);
        ksort($removed);
        ksort($changed);

        $beforeGroups = $before['groups'] ?? [];
        $afterGroups = $after['groups'] ?? [];

        $groupsAdded = array_values(array_diff($afterGroups, $beforeGroups));
        $groupsRemoved = array_values(array_diff($beforeGroups, $afterGroups));
        sort($groupsAdded);
        sort($groupsRemoved);

        return [
            'attributes' => [
                'added' => $added,
                'removed' => $removed,
                'changed' => $changed,
            ],
            'groups' => [
                'added' => $groupsAdded,
                'removed' => $groupsRemoved,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeAttributes(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $key => $value) {
            $name = strtolower((string) $key);

            if (in_array($name, self::REDACTED_ATTRIBUTES, true)) {
                $normalized[$name] = ['__redacted' => true];

                continue;
            }

            $normalized[$name] = $this->normalizeValue($value);
        }

        ksort($normalized);

        return $normalized;
    }

    private function findUser(string $username): ActiveDirectoryUser
    {
        $user = LdapRecordUserService::getUser($username);

        if (! $user instanceof ActiveDirectoryUser) {
            throw new RuntimeException("LDAP-User [{$username}] nicht gefunden.");
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function groupNames(ActiveDirectoryUser $user): array
    {
        try {
            $names = $user->groups()->get()->map(
                fn ($group) => (string) $group->getFirstAttribute('cn')
            )->filter()->values()->all();
        } catch (\Throwable) {
            $memberOf = $user->getAttribute('memberof') ?? [];
            $names = collect(is_array($memberOf) ? $memberOf : [$memberOf])
                ->map(function (mixed $dn): string {
                    if (! is_string($dn) || ! preg_match('/^CN=([^,]+)/i', $dn, $m)) {
                        return '';
                    }

                    return $m[1];
                })
                ->filter()
                ->values()
                ->all();
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $items = array_map(fn (mixed $item): mixed => $this->normalizeScalar($item), $value);

            // Mehrwertige Attribute sortieren für stabile Diffs (außer Reihenfolge ist semantisch).
            $allScalar = collect($items)->every(fn (mixed $item): bool => is_scalar($item) || $item === null);
            if ($allScalar) {
                $stringItems = array_map(fn (mixed $item): string => (string) $item, $items);
                sort($stringItems, SORT_STRING);

                return count($stringItems) === 1 ? $stringItems[0] : array_values($stringItems);
            }

            return array_values($items);
        }

        return $this->normalizeScalar($value);
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Binärdaten (GUID/SID/Fotos) als Base64 markieren.
        if ($value !== '' && ! mb_check_encoding($value, 'UTF-8')) {
            return [
                '__encoding' => 'base64',
                'value' => base64_encode($value),
                'bytes' => strlen($value),
            ];
        }

        return $value;
    }

    private function valuesDiffer(mixed $before, mixed $after): bool
    {
        return json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            !== json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{attributes?: array<string, mixed>, groups?: list<string>, meta?: array<string, mixed>}
     */
    public function loadDumpFile(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Dump-Datei nicht gefunden: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Dump-Datei konnte nicht gelesen werden: {$path}");
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException("Ungültiges Dump-JSON: {$path}");
        }

        return $data;
    }
}
