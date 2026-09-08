<?php

declare(strict_types=1);

use Hwkdo\IntranetAppWorkflows\Database\Seeders\MaAustrittWorkflowSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new MaAustrittWorkflowSeeder)->seed();
    }

    public function down(): void
    {
        // Definition bleibt bewusst erhalten.
    }
};
