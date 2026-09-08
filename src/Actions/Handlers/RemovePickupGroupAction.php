<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;

final class RemovePickupGroupAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly CiscoPickupGatewayInterface $cisco,
    ) {}

    public static function key(): string
    {
        return 'ma_umsetzung.remove_pickup_group';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        // Wenn eine neue Gruppe gesetzt wird, kein Clear.
        if (trim((string) $context->payloadValue('add_pickup_group', '')) !== '') {
            return ActionResult::succeeded('Übersprungen', messages: ['Pickup wird ersetzt statt geleert']);
        }

        $shouldClear = $this->shouldClear($context);
        if (! $shouldClear) {
            return ActionResult::succeeded('Nicht benötigt', messages: ['Pickup bleibt unverändert']);
        }

        $user = $this->resolveUser($context);
        if ($user === null) {
            return ActionResult::failed('Mitarbeiter für Pickup nicht gefunden', retryable: false);
        }

        $pattern = $this->cisco->linePatternForUser($user);
        if ($pattern === null || $pattern === '') {
            return ActionResult::failed(
                'Keine Cisco-Line ermittelbar (Telefonnummer/Pattern) – Pickup nicht entfernbar',
                retryable: false,
            );
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: "Anrufübernahmegruppe auf Line [{$pattern}] entfernen",
            output: ['pickup_group_cleared' => true, 'line_pattern' => $pattern],
        )) {
            return $dry;
        }

        if (! $this->cisco->setPickupGroupForUser($user, '')) {
            return ActionResult::failed("Anrufübernahmegruppe konnte nicht von Line [{$pattern}] entfernt werden");
        }

        return ActionResult::succeeded(
            message: "Anrufübernahmegruppe von Line [{$pattern}] entfernt",
            output: [
                'pickup_group_cleared' => true,
                'line_pattern' => $pattern,
            ],
        );
    }

    private function shouldClear(ActionContext $context): bool
    {
        $vgNein = in_array($context->payloadValue('anrufuebernahme_benoetigt'), [false, 0, '0'], true);
        $adminNein = in_array($context->payloadValue('pickup_uebernehmen'), [false, 0, '0'], true);

        return $vgNein || $adminNein;
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
