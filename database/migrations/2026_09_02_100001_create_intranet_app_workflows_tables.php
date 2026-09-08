<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definition + Runtime in einer Migration, damit up/down atomar sind
 * und Foreign Keys die Rollback-Reihenfolge nicht sprengen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_workflows_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique('iaw_types_key_uq');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('title_resolver')->nullable();
            $table->string('due_date_resolver')->nullable();
            $table->string('department_resolver')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('intranet_app_workflows_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('intranet_app_workflows_types')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('key');
            $table->string('title');
            $table->string('form_component')->nullable();
            $table->string('assignee_resolver')->nullable();
            $table->string('group_resolver')->nullable();
            $table->timestamps();

            $table->unique(['type_id', 'position'], 'iaw_steps_type_position_uq');
            $table->unique(['type_id', 'key'], 'iaw_steps_type_key_uq');
        });

        Schema::create('intranet_app_workflows_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique('iaw_inputs_key_uq');
            $table->string('label');
            $table->string('typ')->default('text');
            $table->text('infotext')->nullable();
            $table->string('output_formatter')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_workflows_step_input', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('intranet_app_workflows_steps')->cascadeOnDelete();
            $table->foreignId('input_id')->constrained('intranet_app_workflows_inputs')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('required')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['step_id', 'input_id'], 'iaw_step_input_step_input_uq');
        });

        Schema::create('intranet_app_workflows_actions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique('iaw_actions_key_uq');
            $table->string('title');
            $table->string('handler_key');
            $table->json('config')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('supports_undo')->default(false);
            $table->boolean('is_idempotent')->default(true);
            $table->timestamps();
        });

        Schema::create('intranet_app_workflows_step_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('intranet_app_workflows_steps')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('intranet_app_workflows_actions')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->boolean('wait_for_due_date')->default(false);
            $table->json('run_when')->nullable();
            $table->json('config_override')->nullable();
            $table->timestamps();

            $table->unique(['step_id', 'position'], 'iaw_step_actions_step_pos_uq');
            $table->unique(['step_id', 'action_id'], 'iaw_step_actions_step_action_uq');
        });

        Schema::create('intranet_app_workflows_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('intranet_app_workflows_types')->cascadeOnDelete();
            $table->unsignedBigInteger('initiator_id');
            $table->unsignedInteger('current_step_position')->default(1);
            $table->string('status')->default('draft');
            $table->json('payload')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('assignee_user_id')->nullable();
            $table->string('assignee_group_key')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'assignee_user_id'], 'iaw_flows_status_assignee_idx');
            $table->index(['status', 'assignee_group_key'], 'iaw_flows_status_group_idx');
        });

        Schema::create('intranet_app_workflows_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('intranet_app_workflows_flows')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('intranet_app_workflows_steps')->nullOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('what');
            $table->json('data')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_workflows_action_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('intranet_app_workflows_flows')->cascadeOnDelete();
            $table->foreignId('step_action_id')->constrained('intranet_app_workflows_step_actions')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('position');
            $table->json('output')->nullable();
            $table->timestamp('waiting_until')->nullable();
            $table->text('latest_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'step_action_id'], 'iaw_runs_flow_step_action_uq');
            $table->index(['status', 'waiting_until'], 'iaw_runs_status_waiting_idx');
        });

        Schema::create('intranet_app_workflows_action_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_run_id')->constrained('intranet_app_workflows_action_runs')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('status')->default('pending');
            $table->json('messages')->nullable();
            $table->json('errors')->nullable();
            $table->string('idempotency_key')->unique('iaw_attempts_idempotency_uq');
            $table->text('exception')->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['action_run_id', 'attempt_no'], 'iaw_attempts_run_attempt_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_workflows_action_attempts');
        Schema::dropIfExists('intranet_app_workflows_action_runs');
        Schema::dropIfExists('intranet_app_workflows_histories');
        Schema::dropIfExists('intranet_app_workflows_flows');
        Schema::dropIfExists('intranet_app_workflows_step_actions');
        Schema::dropIfExists('intranet_app_workflows_step_input');
        Schema::dropIfExists('intranet_app_workflows_actions');
        Schema::dropIfExists('intranet_app_workflows_inputs');
        Schema::dropIfExists('intranet_app_workflows_steps');
        Schema::dropIfExists('intranet_app_workflows_types');
    }
};
