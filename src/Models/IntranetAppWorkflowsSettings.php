<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Models;

use Hwkdo\IntranetAppWorkflows\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppWorkflowsSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppWorkflowsSettings
    {
        return self::orderBy('version', 'desc')->first();
    }
}
