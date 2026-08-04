<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('pipeline_runs')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->string('doc_type', 32)->nullable();
            $table->string('field_path');
            $table->jsonb('ai_value')->nullable();
            $table->jsonb('corrected_value')->nullable();
            $table->timestamp('created_at')->nullable();

            // The query this table exists to serve: "what did the user fix on
            // this merchant's documents before?" — for future few-shot examples.
            $table->index(['owner_id', 'merchant_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_corrections');
    }
};
