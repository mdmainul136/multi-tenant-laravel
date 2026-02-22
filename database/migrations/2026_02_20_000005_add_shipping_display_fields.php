<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds delivery_time and weight_range display fields to ior_shipping_settings.
 * These drive the UI info pills: "7-14 days" and "0.5-30 kg".
 * Also updates seeded rows with default values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ior_shipping_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ior_shipping_settings', 'delivery_time')) {
                $table->string('delivery_time', 50)->default('')->after('is_active')
                      ->comment('Human-readable delivery estimate, e.g. "7-14 days"');
            }
            if (!Schema::hasColumn('ior_shipping_settings', 'weight_range')) {
                $table->string('weight_range', 50)->default('')->after('delivery_time')
                      ->comment('Human-readable weight range, e.g. "0.5-30 kg"');
            }
            if (!Schema::hasColumn('ior_shipping_settings', 'description')) {
                $table->string('description', 200)->default('')->after('weight_range')
                      ->comment('Short description shown in the settings card');
            }
        });

        // Back-fill defaults for seeded rows
        \DB::table('ior_shipping_settings')->where('shipping_method', 'air')->update([
            'delivery_time' => '7-14 days',
            'weight_range'  => '0.5-30 kg',
            'description'   => 'Air freight — faster delivery',
        ]);

        \DB::table('ior_shipping_settings')->where('shipping_method', 'sea')->update([
            'delivery_time' => '30-45 days',
            'weight_range'  => '0.5-30 kg',
            'description'   => 'Sea freight — economical for heavy cargo',
        ]);
    }

    public function down(): void
    {
        Schema::table('ior_shipping_settings', function (Blueprint $table) {
            $table->dropColumn(['delivery_time', 'weight_range', 'description']);
        });
    }
};
