<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Throwable;

/**
 * Stichtag: Asset-Dispositionen außer bereits erledigtem habe_ich_erhalten.
 */
final class ApplyAssetsDispositionsAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.apply_assets_dispositions';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $dispositions = $context->payloadValue('assets_dispositions');
        if (! is_array($dispositions) || $dispositions === []) {
            return ActionResult::succeeded('Keine Asset-Dispositionen');
        }

        $pending = [];
        foreach ($dispositions as $assetId => $row) {
            if (! is_array($row)) {
                continue;
            }
            $choice = (string) ($row['choice'] ?? '');
            if ($choice === '' || $choice === 'habe_ich_erhalten') {
                continue;
            }
            $pending[(string) $assetId] = $row;
        }

        if ($pending === []) {
            return ActionResult::succeeded('Keine Asset-Dispositionen am Stichtag (nur habe_ich_erhalten oder leer)');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Asset-Dispositionen anwenden ('.count($pending).')',
            output: ['assets_dispositions_planned' => $pending],
        )) {
            return $dry;
        }

        $serviceClass = \Hwkdo\IntranetAppAssets\Services\AustrittAssetDispositionService::class;
        if (! class_exists($serviceClass)) {
            return ActionResult::failed('Assets-Package / AustrittAssetDispositionService nicht verfügbar', retryable: false);
        }

        try {
            $payload = array_merge($context->payload, ['flow_id' => $context->flow->id]);
            /** @var list<string> $messages */
            $messages = app($serviceClass)->applyStichtagDispositions($payload, $pending);

            return ActionResult::succeeded(
                message: 'Asset-Dispositionen angewendet',
                messages: $messages,
                output: ['assets_dispositions_applied' => array_keys($pending)],
            );
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Asset-Dispositionen fehlgeschlagen: '.$e->getMessage());
        }
    }
}
