<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration runs on the LANDLORD (MASTER) database.
     */
    public function up(): void
    {
        if (!Schema::hasTable('landlord_ior_restricted_items')) {
            Schema::create('landlord_ior_restricted_items', function (Blueprint $table) {
                $table->id();
                $table->string('keyword')->index();
                $table->string('reason');
                $table->string('severity')->default('warning'); // warning, blocking
                $table->string('origin_country_code', 3)->nullable(); // e.g., CHN, USA. Null = global.
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('landlord_ior_countries')) {
            Schema::create('landlord_ior_countries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 3)->unique()->index(); // ISO 3-letter code
                $table->string('flag_url')->nullable();
                $table->string('default_currency_code', 3)->default('USD');
                
                // Baseline logistics defaults for the platform owner to set
                $table->decimal('default_duty_percent', 5, 2)->default(25.00);
                $table->decimal('default_shipping_rate_per_kg', 8, 2)->default(8.00);
                
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_ior_restricted_items');
        Schema::dropIfExists('landlord_ior_countries');
    }
};
