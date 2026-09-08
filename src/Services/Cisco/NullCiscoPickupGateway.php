<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Cisco;

use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Illuminate\Database\Eloquent\Model;

final class NullCiscoPickupGateway implements CiscoPickupGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var array<int|string, string> */
    public array $pickupByUserId = [];

    /** @var array<string, string> */
    public array $linePatternByUsername = [];

    /** @var list<int|string> */
    public array $missingLineUserIds = [];

    /** Wenn true, muss Pattern in {@see $existingLinePatterns} liegen. */
    public bool $requireExistingLine = false;

    /** @var list<string> */
    public array $existingLinePatterns = [];

    public function linePatternForUser(Model $user): ?string
    {
        $this->calls[] = ['op' => 'linePatternForUser', 'args' => [$user->getKey()]];

        if (in_array($user->getKey(), $this->missingLineUserIds, true)) {
            return null;
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username !== '' && array_key_exists($username, $this->linePatternByUsername)) {
            $pattern = trim($this->linePatternByUsername[$username]);

            return $pattern !== '' ? $pattern : null;
        }

        return 'TEST_PATTERN_'.$user->getKey();
    }

    public function getPickupGroupForUser(Model $user): ?array
    {
        $this->calls[] = ['op' => 'getPickupGroupForUser', 'args' => [$user->getKey()]];
        $pattern = $this->linePatternForUser($user);
        if ($pattern === null) {
            return null;
        }

        if ($this->requireExistingLine && ! in_array($pattern, $this->existingLinePatterns, true)) {
            return null;
        }

        $name = $this->pickupByUserId[$user->getKey()] ?? '';

        return ['name' => $name !== '' ? $name : null];
    }

    public function setPickupGroupForUser(Model $user, string $pickupGroupName): bool
    {
        $this->calls[] = ['op' => 'setPickupGroupForUser', 'args' => [$user->getKey(), $pickupGroupName]];

        $pattern = $this->linePatternForUser($user);
        if ($pattern === null) {
            return false;
        }

        if ($this->requireExistingLine && ! in_array($pattern, $this->existingLinePatterns, true)) {
            return false;
        }

        if ($pickupGroupName === '') {
            unset($this->pickupByUserId[$user->getKey()]);
        } else {
            $this->pickupByUserId[$user->getKey()] = $pickupGroupName;
        }

        return true;
    }
}
