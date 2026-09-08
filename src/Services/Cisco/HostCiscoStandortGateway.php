<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Cisco;

use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use Hwkdo\CiscoPhoneServicesLaravel\Support\AxlValueFormatter;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoStandortGatewayInterface;
use Throwable;

final class HostCiscoStandortGateway implements CiscoStandortGatewayInterface
{
    public function __construct(
        private readonly AxlServiceInterface $axl,
    ) {}

    public function listPhonesForUser(string $userid): array
    {
        try {
            $phones = $this->axl->listPhonesForUser($userid);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $result = [];
        foreach ($phones as $phone) {
            $name = trim((string) ($phone['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $lines = [];
            foreach ($phone['lines'] ?? [] as $line) {
                $pattern = trim((string) ($line['pattern'] ?? ''));
                if ($pattern === '') {
                    continue;
                }
                $lines[] = [
                    'pattern' => $pattern,
                    'route_partition' => trim((string) ($line['route_partition'] ?? '')),
                ];
            }

            $result[] = [
                'name' => $name,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    public function linePatternForUser(object $user): ?string
    {
        try {
            if (! $user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
                return null;
            }

            $pattern = trim((string) $this->axl->getLinePatternForUser($user));

            return $pattern !== '' ? $pattern : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getPhoneConfig(string $phoneName): ?array
    {
        try {
            $phone = $this->axl->getPhone($phoneName);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return [
            'name' => AxlValueFormatter::stringify($phone->name ?? $phoneName),
            'device_pool' => AxlValueFormatter::stringify($phone->devicePoolName ?? ''),
            'location' => AxlValueFormatter::stringify($phone->locationName ?? ''),
            'reroute_css' => AxlValueFormatter::stringify($phone->rerouteCallingSearchSpaceName ?? ''),
        ];
    }

    public function updatePhoneStandort(
        string $phoneName,
        string $devicePool,
        string $location,
        ?string $rerouteCss,
    ): bool {
        try {
            $payload = [
                'devicePoolName' => $devicePool,
                'locationName' => $location,
            ];
            if ($rerouteCss !== null) {
                $payload['rerouteCallingSearchSpaceName'] = $rerouteCss;
            }

            $this->axl->updatePhone($phoneName, $payload);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function applyPhone(string $phoneName): bool
    {
        try {
            $this->axl->applyPhone($phoneName);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function getLineConfig(string $pattern): ?array
    {
        try {
            $line = $this->axl->getLine($pattern);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return [
            'pattern' => AxlValueFormatter::stringify($line->pattern ?? $pattern),
            'calling_search_space' => AxlValueFormatter::stringify($line->shareLineAppearanceCssName ?? ''),
        ];
    }

    public function updateLineCallingSearchSpace(string $pattern, string $callingSearchSpace): bool
    {
        try {
            $this->axl->updateLineByPattern($pattern, [
                'shareLineAppearanceCssName' => $callingSearchSpace,
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
