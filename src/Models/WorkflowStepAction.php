<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStepAction extends Model
{
    protected $table = 'intranet_app_workflows_step_actions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'wait_for_due_date' => 'boolean',
            'run_when' => 'array',
            'config_override' => 'array',
        ];
    }

    /** @return BelongsTo<WorkflowStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    /** @return BelongsTo<WorkflowAction, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'action_id');
    }

    /** @return HasMany<WorkflowActionRun, $this> */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(WorkflowActionRun::class, 'step_action_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedConfig(): array
    {
        $base = $this->action?->config ?? [];
        $override = $this->config_override ?? [];

        return array_replace_recursive(is_array($base) ? $base : [], is_array($override) ? $override : []);
    }
}
