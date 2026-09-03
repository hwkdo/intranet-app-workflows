<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowActionAttempt extends Model
{
    protected $table = 'intranet_app_workflows_action_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ActionRunStatus::class,
            'messages' => 'array',
            'errors' => 'array',
            'retryable' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkflowActionRun, $this> */
    public function actionRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowActionRun::class, 'action_run_id');
    }
}
