<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowType extends Model
{
    protected $table = 'intranet_app_workflows_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'type_id')->orderBy('position');
    }

    /** @return HasMany<WorkflowFlow, $this> */
    public function flows(): HasMany
    {
        return $this->hasMany(WorkflowFlow::class, 'type_id');
    }
}
