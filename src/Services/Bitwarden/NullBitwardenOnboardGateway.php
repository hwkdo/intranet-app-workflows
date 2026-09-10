<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services\Bitwarden;

use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOnboardGatewayInterface;

final class NullBitwardenOnboardGateway implements BitwardenOnboardGatewayInterface
{
    public function inviteByEmail(
        string $email,
        ?string $groupId = null,
        ?string $collectionId = null,
    ): array {
        return [
            'already_member' => false,
            'member_id' => null,
            'invited' => true,
            'group_id' => $groupId,
            'collection_id' => $collectionId,
        ];
    }
}
