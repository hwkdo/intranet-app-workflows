<?php

declare(strict_types=1);

use Hwkdo\IntranetAppWorkflows\Database\Seeders\MaNeuWorkflowSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new MaNeuWorkflowSeeder)->seed();
    }

    public function down(): void
    {
        // Definition bleibt bewusst erhalten.
    }
};
