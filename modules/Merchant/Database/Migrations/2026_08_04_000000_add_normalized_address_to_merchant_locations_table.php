<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Merchant\Services\AddressNormalizer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_locations', function (Blueprint $table) {
            $table->string('normalized_address')->nullable()->after('address');
        });

        DB::statement('CREATE INDEX merchant_locations_normalized_address_trgm_idx ON merchant_locations USING gin (normalized_address gin_trgm_ops)');

        foreach (DB::table('merchant_locations')->select('id', 'address')->cursor() as $row) {
            DB::table('merchant_locations')
                ->where('id', $row->id)
                ->update(['normalized_address' => AddressNormalizer::normalize($row->address)]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS merchant_locations_normalized_address_trgm_idx');

        Schema::table('merchant_locations', function (Blueprint $table) {
            $table->dropColumn('normalized_address');
        });
    }
};
