<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add IOR specific columns to ec_products to unify the catalog
        Schema::table('ec_products', function (Blueprint $table) {
            if (!Schema::hasColumn('ec_products', 'product_type')) {
                $table->string('product_type')->default('local')->after('id')->index(); // local | foreign
            }
            if (!Schema::hasColumn('ec_products', 'ior_attributes')) {
                $table->json('ior_attributes')->nullable()->after('meta_description'); // {original_url, marketplace, last_synced_at}
            }
        });

        // Add IOR exchange rate to currency table if exists
        \DB::table('ec_currencies')->insertOrIgnore([
            'code' => 'BDT',
            'name' => 'Bangladeshi Taka',
            'symbol' => '৳',
            'exchange_rate_to_usd' => 1.0, // Base in BDT for IOR often, or variable
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'ior_attributes']);
        });
    }
};
