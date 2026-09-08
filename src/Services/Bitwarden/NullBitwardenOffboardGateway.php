<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOffboardGatewayInterface;

/**
 * Fallback ohne Bitwarden-API bzw. Test-Double.
 */
final class NullBitwardenOffboardGateway implements BitwardenOffboardGatewayInterface
{
    /** @var list<array{op: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var list<string> */
    public array $memberEmails = [];

    public bool $offboardSucceeds = true;

    public function offboardByEmail(string $email): bool
    {
        $this->calls[] = ['op' => 'offboardByEmail', 'args' => [$email]];

        $needle = strtolower(trim($email));
        $this->memberEmails = array_values(array_filter(
            $this->memberEmails,
            static fn (string $e): bool => strtolower($e) !== $needle,
        ));

        return $this->offboardSucceeds;
    }
}
