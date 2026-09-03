<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $table = 'intranet_app_workflows_steps';

    protected $guarded = [];

    /** @return BelongsTo<WorkflowType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class, 'type_id');
    }

    /** @return HasMany<WorkflowStepAction, $this> */
    public function stepActions(): HasMany
    {
        return $this->hasMany(WorkflowStepAction::class, 'step_id')->orderBy('position');
    }

    /** @return BelongsToMany<WorkflowInput, $this> */
    public function inputs(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkflowInput::class,
            'intranet_app_workflows_step_input',
            'step_id',
            'input_id'
        )->withPivot(['position', 'required', 'config'])->withTimestamps()->orderByPivot('position');
    }
}
