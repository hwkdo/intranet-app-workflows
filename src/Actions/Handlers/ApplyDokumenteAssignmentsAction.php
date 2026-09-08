<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Support\PhaseCGuard;
use Throwable;

final class ApplyDokumenteAssignmentsAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_austritt.apply_dokumente_assignments';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseCGuard::preflight()) {
            return $early;
        }

        $assignments = $context->payloadValue('dokumente_assignments');
        if (! is_array($assignments) || $assignments === []) {
            return ActionResult::succeeded('Keine Dokumente-Zuweisungen');
        }

        if ($dry = PhaseCGuard::assertNotDryRunOrMessage(
            actionLabel: 'Dokumente-Zuweisungen anwenden ('.count($assignments).')',
            output: ['dokumente_assignments_planned' => $assignments],
        )) {
            return $dry;
        }

        if (! class_exists(\Hwkdo\IntranetAppDokumente\Models\Document::class)) {
            return ActionResult::failed('Dokumente-Package nicht verfügbar', retryable: false);
        }

        $messages = [];
        $errors = [];

        foreach ($assignments as $key => $row) {
            if (! is_array($row)) {
                $errors[] = "{$key}: ungültiger Eintrag";

                continue;
            }

            $documentId = (int) ($row['document_id'] ?? preg_replace('/_.*/', '', (string) $key));
            $role = (string) ($row['role'] ?? '');
            $toUserId = $row['to_user_id'] ?? null;

            if ($documentId < 1 || ! in_array($role, ['responsible', 'uploader'], true) || ! is_numeric($toUserId)) {
                $errors[] = "{$key}: document_id/role/to_user_id ungültig";

                continue;
            }

            try {
                /** @var \Hwkdo\IntranetAppDokumente\Models\Document|null $document */
                $document = \Hwkdo\IntranetAppDokumente\Models\Document::query()->find($documentId);
                if (! $document) {
                    $errors[] = "{$key}: Dokument #{$documentId} nicht gefunden";

                    continue;
                }

                if ($role === 'responsible') {
                    $document->responsible_id = (int) $toUserId;
                } else {
                    $document->uploader_id = (int) $toUserId;
                }
                $document->save();
                $messages[] = "Dokument #{$documentId} {$role} → User #{$toUserId}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$key}: ".$e->getMessage();
            }
        }

        if ($errors !== [] && $messages === []) {
            return ActionResult::failed('Dokumente-Zuweisungen fehlgeschlagen', errors: $errors);
        }

        if ($errors !== []) {
            return ActionResult::partial(
                message: 'Dokumente teilweise zugewiesen',
                messages: $messages,
                errors: $errors,
            );
        }

        return ActionResult::succeeded(
            message: count($messages).' Dokumente-Zuweisung(en) angewendet',
            messages: $messages,
        );
    }
}
