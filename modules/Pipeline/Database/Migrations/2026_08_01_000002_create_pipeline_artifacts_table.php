<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pipeline_runs')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('pipeline_run_steps')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('kind', 16);
            $table->jsonb('payload')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'key', 'superseded_at']);
            $table->index(['kind', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_artifacts');
    }
};
