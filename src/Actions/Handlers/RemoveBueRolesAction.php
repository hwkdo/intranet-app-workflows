<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

/**
 * Entfernt explizit gesetzte BuE-Rollen aus Payload `remove_bue_roles`.
 */
final class RemoveBueRolesAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BueRolesGatewayInterface $bue,
    ) {}

    public static function key(): string
    {
        return 'ma_umsetzung.remove_bue_roles';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $roles = $this->rolesFromPayload($context);
        if ($roles === []) {
            return ActionResult::succeeded(message: 'Nicht benötigt', messages: ['Keine BuE-Rollen im Payload (remove_bue_roles)']);
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return ActionResult::failed('Username fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        $bueUsername = $user?->bue_username ?? null;

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'BuE-Rollen entfernen für ['.($bueUsername ?: $username).']: '.implode(', ', $roles),
            output: ['bue_roles_remove' => $roles, 'bue_username' => $bueUsername],
        )) {
            return $dry;
        }

        if (! $user) {
            return ActionResult::failed("Intranet-User [{$username}] nicht gefunden");
        }

        if (! filled($bueUsername)) {
            return ActionResult::failed("User [{$username}] hat keinen bue_username", retryable: false);
        }

        $messages = [];
        $errors = [];

        foreach ($roles as $role) {
            try {
                $this->bue->revokeRole((string) $bueUsername, $role);
                $messages[] = "BuE Rolle {$role} entfernt für BuE-User {$bueUsername}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Fehler bei BuE Rolle {$role}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('BuE-Rollen konnten nicht entfernt werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'BuE-Rollen teilweise entfernt',
                messages: $messages,
                errors: $errors,
                output: ['bue_roles_removed' => $roles],
            );
        }

        return ActionResult::succeeded(
            message: 'BuE-Rollen entfernt',
            output: ['bue_roles_removed' => $roles],
            messages: $messages,
        );
    }

    /**
     * @return list<string>
     */
    private function rolesFromPayload(ActionContext $context): array
    {
        $fromPayload = $context->payloadValue('remove_bue_roles');
        if (is_string($fromPayload) && $fromPayload !== '') {
            $fromPayload = array_filter(array_map(trim(...), explode(',', $fromPayload)));
        }

        if (! is_array($fromPayload) || $fromPayload === []) {
            return [];
        }

        return array_values(array_unique(array_map(strval(...), $fromPayload)));
    }
}
