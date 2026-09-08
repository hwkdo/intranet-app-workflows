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
 * Vergibt explizit gesetzte BuE-Rollen aus Payload `add_bue_roles`.
 *
 * Für ma_neu bewusst nicht verdrahtet: dort reicht das BuE-Ticket mit
 * Analog-Rollenliste (manuelle Admin-Nacharbeit). Genutzt z. B. von ma_umsetzung.
 */
final class AddBueRolesAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BueRolesGatewayInterface $bue,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.add_bue_roles';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $roles = $this->rolesFromPayload($context);
        if ($roles === []) {
            return ActionResult::succeeded(message: 'Nicht benötigt', messages: ['Keine BuE-Rollen im Payload (add_bue_roles)']);
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return ActionResult::failed('Username fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        $bueUsername = $user?->bue_username ?? null;

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'BuE-Rollen für ['.($bueUsername ?: $username).']: '.implode(', ', $roles),
            output: ['bue_roles' => $roles, 'bue_username' => $bueUsername],
        )) {
            return $dry;
        }

        if (! $user) {
            return ActionResult::failed("Intranet-User [{$username}] nicht gefunden – Import zuerst ausführen?");
        }

        if (! filled($bueUsername)) {
            return ActionResult::failed("User [{$username}] hat keinen bue_username", retryable: false);
        }

        $messages = [];
        $errors = [];

        foreach ($roles as $role) {
            try {
                $this->bue->grantRole((string) $bueUsername, $role);
                $messages[] = "BuE Rolle {$role} gesetzt für BuE-User {$bueUsername}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Fehler bei BuE Rolle {$role}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('BuE-Rollen konnten nicht gesetzt werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'BuE-Rollen teilweise gesetzt',
                messages: $messages,
                errors: $errors,
                output: ['bue_roles' => $roles],
            );
        }

        return ActionResult::succeeded(
            message: 'BuE-Rollen gesetzt',
            output: ['bue_roles' => $roles],
            messages: $messages,
        );
    }

    /**
     * @return list<string>
     */
    private function rolesFromPayload(ActionContext $context): array
    {
        $fromPayload = $context->payloadValue('add_bue_roles');
        if (is_string($fromPayload) && $fromPayload !== '') {
            $fromPayload = array_filter(array_map(trim(...), explode(',', $fromPayload)));
        }

        if (! is_array($fromPayload) || $fromPayload === []) {
            return [];
        }

        return array_values(array_unique(array_map(strval(...), $fromPayload)));
    }
}
