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
}
