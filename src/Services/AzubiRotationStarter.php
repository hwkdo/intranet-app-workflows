<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use App\Models\AzubiEinsatz;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use RuntimeException;

/**
 * Erzeugt und schließt einen ma_umsetzung-Flow für eine Azubi-Rotation.
 */
final class AzubiRotationStarter
{
    public function __construct(
        private readonly AzubiRotationPayloadBuilder $payloadBuilder,
        private readonly WorkflowOrchestrator $orchestrator,
    ) {}

    public function findExistingFlow(int $einsatzId): ?WorkflowFlow
    {
        $source = (string) config('intranet-app-workflows.azubi_rotation.source', 'azubi_rotation');

        return WorkflowFlow::query()
            ->where('payload->source', $source)
            ->where('payload->azubi_einsatz_id', $einsatzId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{flow: WorkflowFlow, created: bool}
     */
    public function start(AzubiEinsatz $einsatz, ?int $overrideAbteilungOld = null): array
    {
        $existing = $this->findExistingFlow($einsatz->id);
        if ($existing !== null) {
            return ['flow' => $existing, 'created' => false];
        }

        $type = WorkflowType::query()->where('key', 'ma_umsetzung')->first();
        if ($type === null) {
            throw new RuntimeException('Workflow-Typ ma_umsetzung ist nicht geseedet.');
        }

        $initiator = $this->resolveInitiator();
        $parts = $this->payloadBuilder->build($einsatz, $overrideAbteilungOld);

        $flow = $this->orchestrator->startFlow($type, (int) $initiator->getKey(), $parts['step1']);

        // startFlow schließt Step 1 und geht auf Step 2.
        $flow = $this->orchestrator->submitStep($flow->fresh(['type.steps']), $parts['step2'], (int) $initiator->getKey());
        $flow = $this->orchestrator->submitStep($flow->fresh(['type.steps']), $parts['step3'], (int) $initiator->getKey());
        $flow = $this->orchestrator->submitStep($flow->fresh(['type.steps']), $parts['step4'], (int) $initiator->getKey());

        return ['flow' => $flow->fresh(['type.steps', 'actionRuns']), 'created' => true];
    }

    private function resolveInitiator(): object
    {
        $username = trim((string) config('intranet-app-workflows.azubi_rotation.initiator_username', 'hwkdo454'));
        if ($username === '') {
            throw new RuntimeException('azubi_rotation.initiator_username ist leer.');
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        if ($user === null) {
            throw new RuntimeException("Azubi-Rotation-Initiator [{$username}] nicht gefunden.");
        }

        return $user;
    }
}
