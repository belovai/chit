<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // sha1(provider|customer_reference) — links a utility bill to its
            // predecessor without scanning artifact jsonb across every run.
            $table->string('series_key', 40)->nullable()->after('doc_type_hint');
            $table->index(['owner_id', 'series_key']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'series_key']);
            $table->dropColumn('series_key');
        });
    }
};
