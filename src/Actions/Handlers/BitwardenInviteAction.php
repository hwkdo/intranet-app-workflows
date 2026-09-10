<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use App\Models\Gvp;
use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOnboardGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class BitwardenInviteAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BitwardenOnboardGatewayInterface $bitwarden,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.bitwarden_invite';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $email = $this->resolveEmail($context);
        if ($email === '') {
            return ActionResult::failed(
                'E-Mail für Bitwarden-Invite fehlt (Intranet-Import / User prüfen)',
                retryable: true,
            );
        }

        [$groupId, $collectionId] = $this->resolveGvpBitwardenIds($context);

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Bitwarden-Invite für [{$email}]",
            output: [
                'bitwarden_invite_email' => $email,
                'bitwarden_group_id' => $groupId,
                'bitwarden_collection_id' => $collectionId,
            ],
        )) {
            return $dry;
        }

        try {
            $result = $this->bitwarden->inviteByEmail($email, $groupId, $collectionId);
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Bitwarden-Invite fehlgeschlagen: '.$e->getMessage());
        }

        $message = ($result['already_member'] ?? false)
            ? "Bitwarden-Mitglied [{$email}] bereits vorhanden"
            : "Bitwarden-Invite für [{$email}] gesendet";

        if ($groupId !== null) {
            $message .= ' (GVP-Gruppe gesetzt)';
        } elseif ($collectionId !== null) {
            $message .= ' (Collection-Zugriff gesetzt)';
        }

        return ActionResult::succeeded(
            message: $message,
            output: [
                'bitwarden_invited' => (bool) ($result['invited'] ?? false) || (bool) ($result['already_member'] ?? false),
                'bitwarden_invite_email' => $email,
                'bitwarden_member_id' => $result['member_id'] ?? null,
                'bitwarden_already_member' => (bool) ($result['already_member'] ?? false),
                'bitwarden_group_id' => $result['group_id'] ?? null,
                'bitwarden_collection_id' => $result['collection_id'] ?? null,
                'bitwarden_invite_at' => now()->toIso8601String(),
            ],
        );
    }

    private function resolveEmail(ActionContext $context): string
    {
        $userId = $context->payloadValue('intranet_user_id');
        if (is_numeric($userId)) {
            $user = WorkflowModels::userQuery()->find((int) $userId);
            $email = trim((string) ($user?->email ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username !== '') {
            $user = WorkflowModels::userQuery()->where('username', $username)->first();
            $email = trim((string) ($user?->email ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        return trim((string) $context->payloadValue('mail', ''));
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveGvpBitwardenIds(ActionContext $context): array
    {
        $abteilungId = $context->payloadValue('abteilung');
        if (! is_numeric($abteilungId) || ! class_exists(Gvp::class)) {
            return [null, null];
        }

        $gvp = Gvp::query()->find((int) $abteilungId);
        if ($gvp === null) {
            return [null, null];
        }

        $groupId = $gvp->hasBitwardenGroup() ? (string) $gvp->bitwarden_group_id : null;
        $collectionId = $gvp->hasBitwardenCollection() ? (string) $gvp->bitwarden_collection_id : null;

        return [$groupId, $collectionId];
    }
}
