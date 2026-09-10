<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

/**
 * Host-Adapter: Bitwarden-Org-Mitglied einladen und optional GVP-Gruppe/Collection setzen.
 */
interface BitwardenOnboardGatewayInterface
{
    /**
     * @return array{
     *     already_member: bool,
     *     member_id: string|null,
     *     invited: bool,
     *     group_id: string|null,
     *     collection_id: string|null
     * }
     */
    public function inviteByEmail(
        string $email,
        ?string $groupId = null,
        ?string $collectionId = null,
    ): array;
}
