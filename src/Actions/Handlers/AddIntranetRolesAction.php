<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Spatie\Permission\Models\Role;
use Throwable;

final class AddIntranetRolesAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_neu.add_intranet_roles';
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

        $roles = $this->resolveRoles($context);
        if ($roles === []) {
            return ActionResult::succeeded(message: 'Nicht benötigt', messages: ['Keine Intranet-Rollen zu setzen']);
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Intranet-Rollen für [{$username}]: ".implode(', ', $roles),
            output: ['intranet_roles' => $roles],
        )) {
            return $dry;
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        if (! $user) {
            return ActionResult::failed("Intranet-User [{$username}] nicht gefunden – Import zuerst ausführen?");
        }

        if (! method_exists($user, 'assignRole') || ! method_exists($user, 'hasRole')) {
            return ActionResult::failed('User-Modell unterstützt keine Spatie-Rollen', retryable: false);
        }

        $messages = [];
        $errors = [];

        foreach ($roles as $roleName) {
            try {
                if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                    $errors[] = "Rolle [{$roleName}] existiert nicht";

                    continue;
                }

                if ($user->hasRole($roleName)) {
                    $messages[] = "Intranet Rolle {$roleName} bereits gesetzt für {$username}";

                    continue;
                }

                $user->assignRole($roleName);
                $messages[] = "Intranet Rolle {$roleName} gesetzt für {$username}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Fehler bei Intranet Rolle {$roleName}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Intranet-Rollen konnten nicht gesetzt werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Intranet-Rollen teilweise gesetzt',
                messages: $messages,
                errors: $errors,
                output: ['intranet_roles' => $roles],
            );
        }

        return ActionResult::succeeded(
            message: 'Intranet-Rollen gesetzt',
            output: ['intranet_roles' => $roles],
            messages: $messages,
        );
    }

    /**
     * @return list<string>
     */
    private function resolveRoles(ActionContext $context): array
    {
        if (array_key_exists('add_intranet_roles', $context->payload)) {
            $fromPayload = $context->payloadValue('add_intranet_roles');
            if (is_string($fromPayload) && $fromPayload !== '') {
                $fromPayload = array_filter(array_map(trim(...), explode(',', $fromPayload)));
            }

            if (! is_array($fromPayload)) {
                return [];
            }

            return array_values(array_unique(array_map(strval(...), $fromPayload)));
        }

        $defaults = config('intranet-app-workflows.phase_c.default_intranet_roles', ['Benutzer']);

        return array_values(array_unique(array_map(strval(...), is_array($defaults) ? $defaults : [])));
    }
}
