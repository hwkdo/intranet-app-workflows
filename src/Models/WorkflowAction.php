<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowAction extends Model
{
    protected $table = 'intranet_app_workflows_actions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_mandatory' => 'boolean',
            'supports_undo' => 'boolean',
            'is_idempotent' => 'boolean',
        ];
    }

    /** @return HasMany<WorkflowStepAction, $this> */
    public function stepActions(): HasMany
    {
        return $this->hasMany(WorkflowStepAction::class, 'action_id');
    }
}
