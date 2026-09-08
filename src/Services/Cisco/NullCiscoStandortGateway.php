<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Cisco;

use Hwkdo\IntranetAppWorkflows\Contracts\CiscoStandortGatewayInterface;

final class NullCiscoStandortGateway implements CiscoStandortGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /**
     * @var array<string, list<array{name: string, lines: list<array{pattern: string, route_partition: string}>, device_pool?: string, location?: string, reroute_css?: string}>>
     */
    public array $phonesByUserid = [];

    /** @var array<string, array{pattern: string, calling_search_space: string}> */
    public array $linesByPattern = [];

    /** @var array<string, string> */
    public array $linePatternByUsername = [];

    public function listPhonesForUser(string $userid): array
    {
        $this->calls[] = ['op' => 'listPhonesForUser', 'args' => [$userid]];

        return array_map(
            static fn (array $phone): array => [
                'name' => $phone['name'],
                'lines' => $phone['lines'],
            ],
            $this->phonesByUserid[$userid] ?? [],
        );
    }

    public function linePatternForUser(object $user): ?string
    {
        $username = trim((string) ($user->username ?? ''));
        $this->calls[] = ['op' => 'linePatternForUser', 'args' => [$username]];

        $pattern = $this->linePatternByUsername[$username] ?? null;

        return is_string($pattern) && $pattern !== '' ? $pattern : null;
    }

    public function getPhoneConfig(string $phoneName): ?array
    {
        $this->calls[] = ['op' => 'getPhoneConfig', 'args' => [$phoneName]];

        foreach ($this->phonesByUserid as $phones) {
            foreach ($phones as $phone) {
                if ($phone['name'] === $phoneName) {
                    return [
                        'name' => $phone['name'],
                        'device_pool' => (string) ($phone['device_pool'] ?? ''),
                        'location' => (string) ($phone['location'] ?? ''),
                        'reroute_css' => (string) ($phone['reroute_css'] ?? ''),
                    ];
                }
            }
        }

        return null;
    }

    public function updatePhoneStandort(
        string $phoneName,
        string $devicePool,
        string $location,
        ?string $rerouteCss,
    ): bool {
        $this->calls[] = ['op' => 'updatePhoneStandort', 'args' => [$phoneName, $devicePool, $location, $rerouteCss]];

        foreach ($this->phonesByUserid as $userid => $phones) {
            foreach ($phones as $index => $phone) {
                if ($phone['name'] !== $phoneName) {
                    continue;
                }

                $this->phonesByUserid[$userid][$index]['device_pool'] = $devicePool;
                $this->phonesByUserid[$userid][$index]['location'] = $location;
                if ($rerouteCss !== null) {
                    $this->phonesByUserid[$userid][$index]['reroute_css'] = $rerouteCss;
                }

                return true;
            }
        }

        return false;
    }

    public function applyPhone(string $phoneName): bool
    {
        $this->calls[] = ['op' => 'applyPhone', 'args' => [$phoneName]];

        return $this->getPhoneConfig($phoneName) !== null;
    }

    public function getLineConfig(string $pattern): ?array
    {
        $this->calls[] = ['op' => 'getLineConfig', 'args' => [$pattern]];

        return $this->linesByPattern[$pattern] ?? null;
    }

    public function updateLineCallingSearchSpace(string $pattern, string $callingSearchSpace): bool
    {
        $this->calls[] = ['op' => 'updateLineCallingSearchSpace', 'args' => [$pattern, $callingSearchSpace]];

        if (! isset($this->linesByPattern[$pattern])) {
            return false;
        }

        $this->linesByPattern[$pattern]['calling_search_space'] = $callingSearchSpace;

        return true;
    }
}
