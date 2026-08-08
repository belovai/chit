<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('hash_id')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('label');
            // Laravel `encrypted` cast. Ciphertext is far longer than the key.
            $table->text('api_key');
            $table->string('key_last_four', 4);
            // sha256 of the raw key: not reversible, but searchable — which the
            // encrypted column is not. This is what makes duplicate detection possible.
            $table->string('key_fingerprint', 64);
            $table->string('model', 64);
            $table->jsonb('settings')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(false);
            $table->string('status', 16);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamps();

            $table->unique(['owner_id', 'provider', 'key_fingerprint']);
            $table->index(['owner_id', 'is_active']);
        });

        // The single-active invariant, enforced by Postgres rather than by
        // application code that a second concurrent request could race past.
        DB::statement(
            'CREATE UNIQUE INDEX ai_credentials_one_active_per_user
             ON ai_credentials (owner_id) WHERE is_active',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credentials');
    }
};
