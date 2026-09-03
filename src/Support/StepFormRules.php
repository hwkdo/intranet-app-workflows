<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Support;

use Hwkdo\IntranetAppWorkflows\Enums\HistoryWhat;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowInput;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStep;

final class StepFormRules
{
    /**
     * @param  array<string, mixed>  $form
     */
    public static function isVisible(WorkflowInput $input, array $form): bool
    {
        $when = $input->config['visible_when'] ?? null;

        if (! is_array($when) || $when === []) {
            return true;
        }

        foreach ($when as $field => $expected) {
            $actual = $form[$field] ?? null;

            if ((string) $actual !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    public static function defaultValue(WorkflowInput $input): mixed
    {
        $config = $input->config ?? [];

        if (array_key_exists('default', $config)) {
            return $config['default'];
        }

        return $input->typ === 'ja_nein' ? null : '';
    }

    /**
     * Formularwerte für den aktuellen Schritt (Payload nur nach tatsächlichem Submit).
     *
     * @return array<string, mixed>
     */
    public static function initialFormForStep(WorkflowFlow $flow, WorkflowStep $step): array
    {
        $wasSubmitted = $flow->histories()
            ->where('step_id', $step->id)
            ->where('what', HistoryWhat::Submitted->value)
            ->exists();

        return self::initialForm(
            $step->inputs,
            $wasSubmitted
                ? fn (WorkflowInput $input): mixed => $flow->getPayloadValue($input->key)
                : null,
        );
    }

    /**
     * @param  iterable<int, WorkflowInput>  $inputs
     * @return array<string, mixed>
     */
    public static function initialForm(iterable $inputs, ?callable $existing = null): array
    {
        $form = [];

        foreach ($inputs as $input) {
            $value = $existing !== null ? $existing($input) : null;

            if ($value === null || $value === '') {
                $form[$input->key] = self::defaultValue($input);
            } else {
                $form[$input->key] = $value;
            }
        }

        return $form;
    }

    /**
     * @param  iterable<int, WorkflowInput>  $inputs
     * @param  array<string, mixed>  $form
     * @return array{0: array<string, list<string>>, 1: array<string, string>}
     */
    public static function validation(iterable $inputs, array $form, string $prefix = 'form'): array
    {
        $rules = [];
        $messages = [];

        foreach ($inputs as $input) {
            if (! self::isVisible($input, $form)) {
                continue;
            }

            $required = (bool) ($input->pivot->required ?? false)
                || (bool) ($input->config['required_when_visible'] ?? false);

            if (! $required) {
                continue;
            }

            $field = $prefix.'.'.$input->key;
            $rules[$field] = ['required'];
            $messages[$field.'.required'] = $input->label.' ist erforderlich.';
        }

        return [$rules, $messages];
    }

    /**
     * Leert Werte von Feldern, die aktuell nicht sichtbar sind.
     *
     * @param  iterable<int, WorkflowInput>  $inputs
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function pruneHidden(iterable $inputs, array $form): array
    {
        foreach ($inputs as $input) {
            if (self::isVisible($input, $form)) {
                continue;
            }

            $form[$input->key] = self::defaultValue($input);
        }

        return $form;
    }

    /**
     * Felder, von denen die Sichtbarkeit anderer Felder abhängt → live sync.
     *
     * @param  iterable<int, WorkflowInput>  $inputs
     * @return list<string>
     */
    public static function liveDependencyKeys(iterable $inputs): array
    {
        $keys = [];

        foreach ($inputs as $input) {
            $when = $input->config['visible_when'] ?? null;

            if (! is_array($when)) {
                continue;
            }

            foreach (array_keys($when) as $field) {
                $keys[] = (string) $field;
            }
        }

        return array_values(array_unique($keys));
    }
}
