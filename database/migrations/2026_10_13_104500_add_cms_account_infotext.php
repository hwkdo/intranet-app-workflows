<?php

declare(strict_types=1);

use Hwkdo\IntranetAppWorkflows\Models\WorkflowInput;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WorkflowInput::query()
            ->where('key', 'cms_benoetigt')
            ->update([
                'infotext' => 'CMS (Content Management System) Account wird benötigt um an der Homepage hwk-do.de arbeiten zu können.',
            ]);
    }

    public function down(): void
    {
        WorkflowInput::query()
            ->where('key', 'cms_benoetigt')
            ->update(['infotext' => null]);
    }
};
