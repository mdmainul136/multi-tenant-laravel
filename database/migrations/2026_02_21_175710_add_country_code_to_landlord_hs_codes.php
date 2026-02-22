<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('landlord_ior_hs_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('landlord_ior_hs_codes', 'country_code')) {
                $table->string('country_code', 3)->default('BGD')->index()->after('hs_code');
                
                // Drop existing unique index on hs_code if it exists
                // Note: The index name might vary, but standard is landlord_ior_hs_codes_hs_code_unique
                try {
                    $table->dropUnique(['hs_code']);
                } catch (\Exception $e) {
                    // Index might not exist or have different name
                }

                $table->unique(['hs_code', 'country_code']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landlord_ior_hs_codes', function (Blueprint $table) {
            $table->dropUnique(['hs_code', 'country_code']);
            $table->dropColumn('country_code');
            $table->unique('hs_code');
        });
    }
};
