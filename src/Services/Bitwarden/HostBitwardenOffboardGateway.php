<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Services\VaultwardenAdminApiService;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOffboardGatewayInterface;
use Throwable;

final class HostBitwardenOffboardGateway implements BitwardenOffboardGatewayInterface
{
    public function __construct(
        private readonly BitwardenManagementApiInterface $api,
        private readonly VaultwardenAdminApiService $adminApi,
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
            $userId = null;

            foreach ($members as $member) {
                if (! is_array($member)) {
                    continue;
                }

                $memberEmail = strtolower(trim((string) ($member['email'] ?? '')));
                if ($memberEmail === $needle) {
                    $memberId = (string) ($member['id'] ?? '');
                    $rawUserId = $member['userId'] ?? null;
                    $userId = is_string($rawUserId) && trim($rawUserId) !== ''
                        ? trim($rawUserId)
                        : null;
                    break;
                }
            }

            if ($memberId === null || $memberId === '') {
                return true;
            }

            // Vollständiges Konto löschen, wenn eine globale User-ID vorhanden ist.
            // Offene Einladungen ohne userId: nur Org-Mitgliedschaft entfernen.
            if ($userId !== null) {
                $this->adminApi->deleteUserAccount($userId);
            } else {
                $this->api->deleteMember($memberId);
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
