<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Throwable;

/**
 * Sofort bei Step-2-Abschluss: Assets mit Choice habe_ich_erhalten → Owner = VG.
 */
final class ApplyAssetHabeIchErhaltenAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.apply_asset_habe_ich_erhalten';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $vgUserId = $this->resolveVorgesetzterUserId($context);
        if ($vgUserId === null) {
            return ActionResult::failed(
                'Vorgesetzter (vorgesetzter_user_id) fehlt im Payload – CaptureAustrittSupervisorMetaAction prüfen',
                retryable: false,
            );
        }

        $dispositions = $context->payloadValue('assets_dispositions');
        if (! is_array($dispositions) || $dispositions === []) {
            return ActionResult::succeeded('Keine Assets mit habe_ich_erhalten');
        }

        $targets = [];
        foreach ($dispositions as $assetId => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['choice'] ?? '') === 'habe_ich_erhalten') {
                $targets[] = (int) $assetId;
            }
        }

        if ($targets === []) {
            return ActionResult::succeeded('Keine Assets mit habe_ich_erhalten');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Assets habe_ich_erhalten → VG #'.$vgUserId.' ('.count($targets).')',
            output: [
                'habe_ich_erhalten_asset_ids' => $targets,
                'vorgesetzter_user_id' => $vgUserId,
            ],
        )) {
            return $dry;
        }

        $serviceClass = \Hwkdo\IntranetAppAssets\Services\AustrittAssetDispositionService::class;
        if (! class_exists($serviceClass) || ! class_exists(\Hwkdo\IntranetAppAssets\Models\Asset::class)) {
            return ActionResult::failed('Assets-Package nicht verfügbar', retryable: false);
        }

        $service = app($serviceClass);
        $messages = [];
        $errors = [];

        foreach ($targets as $assetId) {
            try {
                /** @var \Hwkdo\IntranetAppAssets\Models\Asset|null $asset */
                $asset = \Hwkdo\IntranetAppAssets\Models\Asset::query()->find($assetId);
                if (! $asset) {
                    $errors[] = "Asset #{$assetId} nicht gefunden";

                    continue;
                }

                $service->habeIchErhalten($asset, $vgUserId);
                $messages[] = "Asset #{$assetId} → VG #{$vgUserId}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Asset #{$assetId}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('habe_ich_erhalten fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'habe_ich_erhalten teilweise angewendet',
                messages: $messages,
                errors: $errors,
                output: ['vorgesetzter_user_id' => $vgUserId],
            );
        }

        return ActionResult::succeeded(
            message: count($messages).' Asset(s) an VG übergeben',
            messages: $messages,
            output: [
                'habe_ich_erhalten_asset_ids' => $targets,
                'vorgesetzter_user_id' => $vgUserId,
            ],
        );
    }

    private function resolveVorgesetzterUserId(ActionContext $context): ?int
    {
        foreach (['vorgesetzter_user_id', 'step2_actor_user_id'] as $key) {
            $raw = $context->payloadValue($key);
            if (is_numeric($raw) && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        $assigneeId = $context->flow->assignee_user_id ?? null;
        if (is_numeric($assigneeId) && (int) $assigneeId > 0) {
            return (int) $assigneeId;
        }

        return null;
    }
}
