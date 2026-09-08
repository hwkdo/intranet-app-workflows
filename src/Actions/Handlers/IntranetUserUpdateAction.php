<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Throwable;

final class IntranetUserUpdateAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_umsetzung.intranet_user_update';
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

        $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
        if (! $user) {
            return ActionResult::failed('Mitarbeiter nicht gefunden', retryable: false);
        }

        $changes = $this->plannedChanges($context, $user);
        if ($changes === []) {
            return ActionResult::succeeded('Keine Intranet-User-Änderungen nötig');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Intranet-User aktualisieren ('.count($changes).' Felder)',
            output: ['intranet_user_update_planned' => $changes],
        )) {
            return $dry;
        }

        $messages = [];
        $errors = [];

        foreach ($changes as $attribute => $value) {
            try {
                $user->{$attribute} = $value;
                $user->save();
                $messages[] = "{$attribute} → ".(is_scalar($value) ? (string) $value : json_encode($value));
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$attribute}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Intranet-User konnte nicht aktualisiert werden', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Intranet-User teilweise aktualisiert',
                messages: $messages,
                errors: $errors,
                output: ['intranet_user_updated' => array_keys($changes)],
            );
        }

        return ActionResult::succeeded(
            message: 'Intranet-User aktualisiert',
            messages: $messages,
            output: ['intranet_user_updated' => array_keys($changes)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function plannedChanges(ActionContext $context, object $user): array
    {
        $map = [
            'raum' => 'raum',
            'standort' => 'standort_id',
            'telefon' => 'telefon',
            'fax' => 'fax',
            'cms_benoetigt' => 'cms_redaktion',
            'abteilung' => 'gvp_id',
        ];

        $changes = [];
        foreach ($map as $payloadKey => $attribute) {
            if (! array_key_exists($payloadKey, $context->payload)) {
                continue;
            }

            $newValue = $context->payloadValue($payloadKey);
            if ($payloadKey === 'cms_benoetigt') {
                $newValue = in_array($newValue, [true, 1, '1'], true) ? 1 : 0;
            }

            $current = $user->{$attribute} ?? null;
            if ((string) $current !== (string) $newValue) {
                $changes[$attribute] = $newValue;
            }
        }

        return $changes;
    }
}
