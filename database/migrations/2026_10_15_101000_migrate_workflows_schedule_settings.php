<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('intranet_background_activity_configs') || ! Schema::hasTable('intranet_app_workflows_settings')) {
            return;
        }

        $row = DB::table('intranet_app_workflows_settings')->orderByDesc('id')->first();

        if ($row === null) {
            return;
        }

        $settings = json_decode((string) $row->settings, true);

        if (! is_array($settings) || ! array_key_exists('automatische_azubi_rotationen', $settings)) {
            return;
        }

        DB::table('intranet_background_activity_configs')->updateOrInsert(
            ['activity_key' => 'workflows.azubi-rotation'],
            [
                'enabled' => (bool) $settings['automatische_azubi_rotationen'],
                'cadence' => BackgroundActivityCadence::DailyAt->value,
                'every' => 1,
                'offset' => 0,
                'at' => '01:10',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('intranet_background_activity_configs')) {
            return;
        }

        DB::table('intranet_background_activity_configs')->where('activity_key', 'workflows.azubi-rotation')->delete();
    }
};
