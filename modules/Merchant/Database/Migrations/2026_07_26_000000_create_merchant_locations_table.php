<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_locations', function (Blueprint $table) {
            $table->id();
            $table->string('hash_id')->unique()->index();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_locations');
    }
};
