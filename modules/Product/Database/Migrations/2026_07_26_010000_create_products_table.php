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
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('hash_id')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX products_owner_id_name_unique ON products (owner_id, lower(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX products_name_trgm_idx ON products USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
