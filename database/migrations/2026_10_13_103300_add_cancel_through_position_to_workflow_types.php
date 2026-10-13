<?php

declare(strict_types=1);

use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_workflows_types', function (Blueprint $table): void {
            $table->unsignedInteger('cancel_through_position')->nullable()->after('is_active');
        });

        WorkflowType::query()->where('key', 'ma_neu')->update(['cancel_through_position' => 2]);
        WorkflowType::query()->where('key', 'ma_umsetzung')->update(['cancel_through_position' => 3]);
        WorkflowType::query()->where('key', 'ma_austritt')->update(['cancel_through_position' => 1]);
    }

    public function down(): void
    {
        Schema::table('intranet_app_workflows_types', function (Blueprint $table): void {
            $table->dropColumn('cancel_through_position');
        });
    }
};
