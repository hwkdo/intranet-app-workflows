<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOffboardGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class BitwardenOffboardAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BitwardenOffboardGatewayInterface $bitwarden,
    ) {}

    public static function key(): string
    {
        return 'ma_austritt.bitwarden_offboard';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $email = $this->resolveEmail($context);
        if ($email === '') {
            return ActionResult::failed('E-Mail für Bitwarden-Offboard fehlt', retryable: false);
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Bitwarden-Offboard für [{$email}]",
            output: ['bitwarden_offboard_email' => $email],
        )) {
            return $dry;
        }

        try {
            if (! $this->bitwarden->offboardByEmail($email)) {
                return ActionResult::failed("Bitwarden-Offboard für [{$email}] fehlgeschlagen");
            }
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Bitwarden-Offboard fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Bitwarden-Offboard für [{$email}] erledigt",
            output: [
                'bitwarden_offboarded' => true,
                'bitwarden_offboard_email' => $email,
            ],
        );
    }

    private function resolveEmail(ActionContext $context): string
    {
        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return '';
        }

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);

        return trim((string) ($user?->email ?? ''));
    }
}
