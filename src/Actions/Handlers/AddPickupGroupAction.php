<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

final class AddPickupGroupAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly CiscoPickupGatewayInterface $cisco,
    ) {}

    public static function key(): string
    {
        return 'ma_umsetzung.add_pickup_group';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $pickup = trim((string) $context->payloadValue('add_pickup_group', ''));
        if ($pickup === '') {
            return ActionResult::succeeded('Nicht benötigt', messages: ['Keine Anrufübernahmegruppe zu setzen']);
        }

        $user = $this->resolveUser($context);
        if ($user === null) {
            return ActionResult::failed('Mitarbeiter für Pickup nicht gefunden', retryable: false);
        }

        $pattern = $this->cisco->linePatternForUser($user);
        if ($pattern === null || $pattern === '') {
            return ActionResult::failed(
                'Keine Cisco-Line ermittelbar (Telefonnummer/Pattern) – Pickup nicht setzbar',
                retryable: false,
            );
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Anrufübernahmegruppe [{$pickup}] auf Line [{$pattern}] setzen",
            output: ['add_pickup_group' => $pickup, 'line_pattern' => $pattern],
        )) {
            return $dry;
        }

        if (! $this->cisco->setPickupGroupForUser($user, $pickup)) {
            return ActionResult::failed(
                "Anrufübernahmegruppe [{$pickup}] konnte nicht auf Line [{$pattern}] gesetzt werden",
            );
        }

        return ActionResult::succeeded(
            message: "Anrufübernahmegruppe [{$pickup}] auf Line [{$pattern}] gesetzt",
            output: [
                'pickup_group_set' => $pickup,
                'line_pattern' => $pattern,
            ],
        );
    }

    private function resolveUser(ActionContext $context): ?object
    {
        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (is_numeric($mitarbeiterId)) {
            $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
            if ($user) {
                return $user;
            }
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return null;
        }

        return WorkflowModels::userQuery()->where('username', $username)->first();
    }
}
