<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('hash_id')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('disk', 64);
            $table->string('path');
            $table->string('file_hash', 64);
            $table->string('mime', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('doc_type', 32)->nullable();
            $table->string('doc_type_hint', 32)->nullable();
            $table->foreignId('current_run_id')->nullable()->constrained('pipeline_runs')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('status', 32);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['owner_id', 'status', 'created_at']);
            // Exact-duplicate detection is a per-owner question, and this index
            // is what makes the dedupe_file_hash step a single cheap lookup.
            $table->index(['owner_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
