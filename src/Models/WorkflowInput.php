<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkflowInput extends Model
{
    protected $table = 'intranet_app_workflows_inputs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /** @return BelongsToMany<WorkflowStep, $this> */
    public function steps(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkflowStep::class,
            'intranet_app_workflows_step_input',
            'input_id',
            'step_id'
        )->withPivot(['position', 'required', 'config'])->withTimestamps();
    }
}
