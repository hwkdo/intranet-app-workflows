<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class RemoveIntranetRolesAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_umsetzung.remove_intranet_roles';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return ActionResult::failed('Username fehlt im Payload', retryable: false);
        }

        $roles = $this->normalizeRoles($context->payloadValue('remove_intranet_roles'));
        $protected = array_map(
            strval(...),
            (array) config('intranet-app-workflows.phase_c.protected_intranet_roles', []),
        );
        $roles = array_values(array_filter(
            $roles,
            static fn (string $role): bool => ! in_array($role, $protected, true),
        ));

        if ($roles === []) {
            return ActionResult::succeeded(message: 'Nicht benötigt', messages: ['Keine Intranet-Rollen zu entfernen']);
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Intranet-Rollen entfernen für [{$username}]: ".implode(', ', $roles),
            output: ['intranet_roles_remove' => $roles],
        )) {
            return $dry;
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        if (! $user) {
            return ActionResult::failed("Intranet-User [{$username}] nicht gefunden");
        }

        if (! method_exists($user, 'removeRole') || ! method_exists($user, 'hasRole')) {
            return ActionResult::failed('User-Modell unterstützt keine Spatie-Rollen', retryable: false);
        }

        $messages = [];
        $errors = [];

        foreach ($roles as $roleName) {
            try {
                if (! $user->hasRole($roleName)) {
                    $messages[] = "Intranet Rolle {$roleName} war nicht gesetzt";

                    continue;
                }

                $user->removeRole($roleName);
                $messages[] = "Intranet Rolle {$roleName} entfernt für {$username}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Fehler bei Intranet Rolle {$roleName}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Intranet-Rollen konnten nicht entfernt werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Intranet-Rollen teilweise entfernt',
                messages: $messages,
                errors: $errors,
                output: ['intranet_roles_removed' => $roles],
            );
        }

        return ActionResult::succeeded(
            message: 'Intranet-Rollen entfernt',
            output: ['intranet_roles_removed' => $roles],
            messages: $messages,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeRoles(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = array_filter(array_map(trim(...), explode(',', $raw)));
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_map(strval(...), $raw)));
    }
}
