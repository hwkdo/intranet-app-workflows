<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use App\Models\Beauftragtenwesen;
use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Throwable;

final class ApplyBeauftragungenAssignmentsAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.apply_beauftragungen_assignments';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $mitarbeiterId = $context->payloadValue('mitarbeiter');
        if (! is_numeric($mitarbeiterId)) {
            return ActionResult::failed('Mitarbeiter fehlt im Payload', retryable: false);
        }

        $leavingId = (int) $mitarbeiterId;
        $assignments = $context->payloadValue('beauftragungen_assignments');
        if (! is_array($assignments) || $assignments === []) {
            return ActionResult::succeeded('Keine Beauftragungs-Zuweisungen');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Beauftragungs-Zuweisungen anwenden ('.count($assignments).')',
            output: ['beauftragungen_assignments_planned' => $assignments],
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];

        foreach ($assignments as $key => $row) {
            if (! is_array($row)) {
                $errors[] = "{$key}: ungültiger Eintrag";

                continue;
            }

            $bwId = (int) ($row['bw_id'] ?? $key);
            $toUserId = $row['to_user_id'] ?? null;
            $action = (string) ($row['action'] ?? '');

            if ($bwId < 1) {
                $errors[] = "{$key}: bw_id ungültig";

                continue;
            }

            try {
                $bw = Beauftragtenwesen::query()->find($bwId);
                if (! $bw) {
                    $errors[] = "{$key}: Beauftragung #{$bwId} nicht gefunden";

                    continue;
                }

                if (is_numeric($toUserId) && (int) $toUserId > 0 && $action !== 'remove') {
                    $bw->users()->syncWithoutDetaching([(int) $toUserId]);
                    $messages[] = "BW #{$bwId}: attach User #{$toUserId}";
                }

                $bw->users()->detach($leavingId);
                $messages[] = "BW #{$bwId}: detach User #{$leavingId}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$key}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Beauftragungs-Zuweisungen fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Beauftragungen teilweise zugewiesen',
                messages: $messages,
                errors: $errors,
            );
        }

        return ActionResult::succeeded(
            message: 'Beauftragungs-Zuweisungen angewendet',
            messages: $messages,
        );
    }
}
