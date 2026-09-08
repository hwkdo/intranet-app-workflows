<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowActionRun extends Model
{
    protected $table = 'intranet_app_workflows_action_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ActionRunStatus::class,
            'output' => 'array',
            'waiting_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkflowFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(WorkflowFlow::class, 'flow_id');
    }

    /** @return BelongsTo<WorkflowStepAction, $this> */
    public function stepAction(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepAction::class, 'step_action_id');
    }

    /** @return HasMany<WorkflowActionAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(WorkflowActionAttempt::class, 'action_run_id')->orderBy('attempt_no');
    }

    /**
     * Debug-/Info-Zeilen aus dem Attempt, der zum aktuellen Run-Status passt.
     *
     * @return list<string>
     */
    public function detailLines(): array
    {
        $attempts = $this->relationLoaded('attempts')
            ? $this->attempts
            : $this->attempts()->orderBy('attempt_no')->get();

        $attempt = $attempts
            ->reverse()
            ->first(fn (WorkflowActionAttempt $a): bool => $a->status === $this->status);

        // Kein passender Attempt (z. B. Status manuell gesetzt) → Output, nicht alten Fehler zeigen.
        if ($attempt === null) {
            return self::flattenOutputLines(is_array($this->output) ? $this->output : []);
        }

        $lines = [];

        foreach ($attempt->messages ?? [] as $message) {
            if (is_string($message) && trim($message) !== '') {
                $lines[] = trim($message);
            }
        }

        foreach ($attempt->errors ?? [] as $error) {
            if (is_string($error) && trim($error) !== '') {
                $lines[] = 'Fehler: '.trim($error);
            }
        }

        if ($lines !== []) {
            return array_values(array_unique($lines));
        }

        return self::flattenOutputLines(is_array($this->output) ? $this->output : []);
    }

    /**
     * @param  array<string, mixed>  $output
     * @return list<string>
     */
    public static function flattenOutputLines(array $output): array
    {
        $lines = [];

        foreach ($output as $key => $value) {
            if (is_bool($value)) {
                $lines[] = $key.': '.($value ? 'true' : 'false');

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $lines[] = $key.': '.(string) $value;

                continue;
            }

            if (is_array($value)) {
                $allScalar = array_reduce(
                    $value,
                    static fn (bool $carry, mixed $item): bool => $carry && (is_scalar($item) || $item === null),
                    true,
                );

                if ($allScalar) {
                    $joined = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
                    $lines[] = $key.': '.($joined !== '' ? $joined : '—');

                    continue;
                }

                $lines[] = $key.': '.json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $lines;
    }
}
