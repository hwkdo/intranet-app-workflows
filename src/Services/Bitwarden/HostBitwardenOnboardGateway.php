<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;
use Hwkdo\BitwardenLaravel\Support\OrganizationMemberStatus;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOnboardGatewayInterface;
use RuntimeException;
use Throwable;

final class HostBitwardenOnboardGateway implements BitwardenOnboardGatewayInterface
{
    public function __construct(
        private readonly BitwardenManagementApiInterface $api,
    ) {}

    public function inviteByEmail(
        string $email,
        ?string $groupId = null,
        ?string $collectionId = null,
    ): array {
        $needle = strtolower(trim($email));
        if ($needle === '') {
            throw new RuntimeException('E-Mail für Bitwarden-Invite fehlt.');
        }

        $groupId = $groupId !== null && trim($groupId) !== '' ? trim($groupId) : null;
        $collectionId = $collectionId !== null && trim($collectionId) !== '' ? trim($collectionId) : null;

        $existing = $this->findMemberByEmail($needle);
        $alreadyMember = $existing !== null;
        $memberId = $existing !== null ? OrganizationMemberStatus::id($existing) : null;
        $invited = false;

        if (! $alreadyMember) {
            $collections = [];
            if ($collectionId !== null && $groupId === null) {
                $collections[] = [
                    'id' => $collectionId,
                    'readOnly' => false,
                    'hidePasswords' => false,
                    'manage' => false,
                ];
            }

            $this->api->inviteMembers([
                'emails' => [$email],
                'type' => '2',
                'accessAll' => false,
                'collections' => $collections,
                'groups' => $groupId !== null ? [$groupId] : [],
            ]);
            $invited = true;

            $existing = $this->findMemberByEmail($needle);
            $memberId = $existing !== null ? OrganizationMemberStatus::id($existing) : null;
        }

        if ($memberId !== null && $memberId !== '' && $groupId !== null) {
            $this->ensureGroupMembership($memberId, $groupId);
        }

        if ($memberId !== null && $memberId !== '' && $collectionId !== null && $groupId === null) {
            $this->ensureCollectionAccess($memberId, $collectionId);
        }

        return [
            'already_member' => $alreadyMember,
            'member_id' => $memberId !== '' ? $memberId : null,
            'invited' => $invited,
            'group_id' => $groupId,
            'collection_id' => $collectionId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMemberByEmail(string $emailLower): ?array
    {
        foreach (OrganizationMemberStatus::unwrapMembers($this->api->getMembers()) as $member) {
            if (OrganizationMemberStatus::email($member) === $emailLower) {
                return $member;
            }
        }

        return null;
    }

    private function ensureGroupMembership(string $memberId, string $groupId): void
    {
        try {
            $userIds = $this->api->getGroupUsers($groupId);
        } catch (Throwable $e) {
            report($e);
            $userIds = [];
        }

        $normalized = [];
        foreach ($userIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $normalized[$id] = $id;
            }
        }
        $normalized[$memberId] = $memberId;

        $this->api->updateGroupUsers($groupId, array_values($normalized));
    }

    private function ensureCollectionAccess(string $memberId, string $collectionId): void
    {
        try {
            $member = $this->api->getMember($memberId, includeCollections: true);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $collections = $member['collections'] ?? [];
        if (! is_array($collections)) {
            $collections = [];
        }

        foreach ($collections as $collection) {
            if (is_array($collection) && (string) ($collection['id'] ?? '') === $collectionId) {
                return;
            }
        }

        $collections[] = [
            'id' => $collectionId,
            'readOnly' => false,
            'hidePasswords' => false,
            'manage' => false,
        ];

        $this->api->updateMember($memberId, [
            'type' => $member['type'] ?? 2,
            'accessAll' => (bool) ($member['accessAll'] ?? false),
            'collections' => $collections,
            'groups' => $member['groups'] ?? [],
        ]);
    }
}
