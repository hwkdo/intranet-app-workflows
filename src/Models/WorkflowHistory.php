<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Hwkdo\IntranetAppWorkflows\Enums\HistoryWhat;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowHistory extends Model
{
    protected $table = 'intranet_app_workflows_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'what' => HistoryWhat::class,
        ];
    }

    /** @return BelongsTo<WorkflowFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(WorkflowFlow::class, 'flow_id');
    }

    /** @return BelongsTo<WorkflowStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(WorkflowModels::user(), 'user_id');
    }
}
