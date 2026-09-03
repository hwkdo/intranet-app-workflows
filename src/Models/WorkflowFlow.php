<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowFlow extends Model
{
    protected $table = 'intranet_app_workflows_flows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => FlowStatus::class,
            'due_date' => 'date',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkflowType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class, 'type_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(WorkflowModels::user(), 'initiator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(WorkflowModels::user(), 'assignee_user_id');
    }

    /** @return HasMany<WorkflowHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'flow_id');
    }

    /** @return HasMany<WorkflowActionRun, $this> */
    public function actionRuns(): HasMany
    {
        return $this->hasMany(WorkflowActionRun::class, 'flow_id');
    }

    public function currentStep(): ?WorkflowStep
    {
        return $this->type
            ?->steps()
            ->where('position', $this->current_step_position)
            ->first();
    }

    public function stepByPosition(int $position): ?WorkflowStep
    {
        return $this->type
            ?->steps()
            ->where('position', $position)
            ->first();
    }

    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return ($this->payload ?? [])[$key] ?? $default;
    }
}
