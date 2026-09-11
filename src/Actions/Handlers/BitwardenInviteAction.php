<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use App\Models\Gvp;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenMemberEligibility;
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

        $skipReason = $this->resolveInviteSkipReason($context);
        if ($skipReason !== null) {
            $email = $this->resolveEmail($context);
            $label = match ($skipReason) {
                'azubi' => 'Azubi',
                'praktikant' => 'Praktikant',
                'excluded' => 'ausgeschlossen',
                default => $skipReason,
            };

            return ActionResult::succeeded(
                message: $email !== ''
                    ? "Bitwarden-Invite für [{$email}] übersprungen ({$label})"
                    : "Bitwarden-Invite übersprungen ({$label})",
                output: [
                    'bitwarden_invited' => false,
                    'bitwarden_invite_skipped' => true,
                    'bitwarden_invite_skip_reason' => $skipReason,
                    'bitwarden_invite_email' => $email !== '' ? $email : null,
                ],
            );
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
        $user = $this->resolveUser($context);
        $email = trim((string) ($user?->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) $context->payloadValue('mail', ''));
    }

    private function resolveUser(ActionContext $context): ?object
    {
        $userId = $context->payloadValue('intranet_user_id');
        if (is_numeric($userId)) {
            $user = WorkflowModels::userQuery()->find((int) $userId);
            if ($user !== null) {
                return $user;
            }
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username !== '') {
            return WorkflowModels::userQuery()->where('username', $username)->first();
        }

        return null;
    }

    private function resolveGvp(ActionContext $context): ?Gvp
    {
        $abteilungId = $context->payloadValue('abteilung');
        if (! is_numeric($abteilungId) || ! class_exists(Gvp::class)) {
            return null;
        }

        return Gvp::query()->find((int) $abteilungId);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveGvpBitwardenIds(ActionContext $context): array
    {
        $gvp = $this->resolveGvp($context);
        if ($gvp === null) {
            return [null, null];
        }

        $groupId = $gvp->hasBitwardenGroup() ? (string) $gvp->bitwarden_group_id : null;
        $collectionId = $gvp->hasBitwardenCollection() ? (string) $gvp->bitwarden_collection_id : null;

        return [$groupId, $collectionId];
    }

    private function resolveInviteSkipReason(ActionContext $context): ?string
    {
        if (! class_exists(BitwardenMemberEligibility::class)) {
            return null;
        }

        $gvp = $this->resolveGvp($context);
        if ($gvp === null) {
            return null;
        }

        $user = $this->resolveUser($context);

        $probe = (object) [
            'id' => $user?->id,
            'azubi' => $user !== null && BitwardenMemberEligibility::isAzubi($user),
            'praktikant' => $user !== null && BitwardenMemberEligibility::isPraktikant($user),
        ];

        if ($this->isTruthy($context->payloadValue('istazubi'))) {
            $probe->azubi = true;
        }

        if ($this->isTruthy($context->payloadValue('istpraktikant'))) {
            $probe->praktikant = true;
        }

        return BitwardenMemberEligibility::inviteSkipReason($probe, $gvp);
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
