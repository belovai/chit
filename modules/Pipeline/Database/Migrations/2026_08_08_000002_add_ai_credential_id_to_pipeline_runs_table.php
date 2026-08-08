<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The engine stays generic: it carries an opaque id it never dereferences.
     * Only the step that needs a model call resolves it, through the Ai module.
     */
    public function up(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->foreignId('ai_credential_id')->nullable()->after('owner_id')
                ->constrained('ai_credentials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_credential_id');
        });
    }
};
