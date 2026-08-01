<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->string('hash_id')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('definition_key', 64);
            $table->unsignedInteger('definition_version');
            // Ordered stage names. stage_position on a step is the index into this list.
            $table->jsonb('stages');
            $table->string('status', 32);
            $table->string('trigger_source', 32);
            $table->foreignId('retried_from_run_id')->nullable()->constrained('pipeline_runs')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('cost_usd_micros')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->jsonb('error_summary')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['owner_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
