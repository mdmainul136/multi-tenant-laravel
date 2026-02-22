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
        Schema::table('ec_products', function (Blueprint $table) {
            if (!Schema::hasColumn('ec_products', 'country_availability')) {
                $table->json('country_availability')->nullable()->after('is_warehouse_verified');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('country_availability');
        });
    }
};
