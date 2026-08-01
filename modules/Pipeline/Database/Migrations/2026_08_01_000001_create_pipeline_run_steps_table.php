<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pipeline_runs')->cascadeOnDelete();
            $table->string('step_key', 64);
            $table->string('stage', 64);
            $table->unsignedSmallInteger('stage_position');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->string('status', 32);
            $table->jsonb('depends_on')->default('[]');
            $table->boolean('allow_failure')->default(false);
            $table->boolean('is_gate')->default(false);
            $table->jsonb('config')->nullable();
            $table->foreignId('added_by_step_id')->nullable()->constrained('pipeline_run_steps')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->jsonb('findings')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cost_usd_micros')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'step_key', 'attempt']);
            $table->index(['run_id', 'stage_position', 'position']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_run_steps');
    }
};
