<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOffboardGatewayInterface;
use Throwable;

final class HostBitwardenOffboardGateway implements BitwardenOffboardGatewayInterface
{
    public function __construct(
        private readonly BitwardenManagementApiInterface $api,
    ) {}

    public function offboardByEmail(string $email): bool
    {
        $needle = strtolower(trim($email));
        if ($needle === '') {
            return true;
        }

        try {
            $members = $this->api->getMembers();
            $memberId = null;

            foreach ($members as $member) {
                if (! is_array($member)) {
                    continue;
                }

                $memberEmail = strtolower(trim((string) ($member['email'] ?? '')));
                if ($memberEmail === $needle) {
                    $memberId = (string) ($member['id'] ?? '');
                    break;
                }
            }

            if ($memberId === null || $memberId === '') {
                return true;
            }

            $this->api->deleteMember($memberId);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
