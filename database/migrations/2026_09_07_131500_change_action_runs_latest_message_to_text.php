<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_workflows_action_runs', function (Blueprint $table): void {
            $table->text('latest_message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_workflows_action_runs', function (Blueprint $table): void {
            $table->string('latest_message')->nullable()->change();
        });
    }
};
