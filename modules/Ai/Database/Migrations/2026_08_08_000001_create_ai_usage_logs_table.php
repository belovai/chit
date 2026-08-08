<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_credential_id')->nullable()->constrained('ai_credentials')->nullOnDelete();
            // Denormalised so a deleted credential still leaves readable history.
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('purpose', 64);
            $table->nullableMorphs('subject');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('cost_usd_micros')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
