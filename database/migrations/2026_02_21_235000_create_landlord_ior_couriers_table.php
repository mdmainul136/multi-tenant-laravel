<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations on the LANDLORD (MASTER) database.
     */
    public function up(): void
    {
        if (!Schema::hasTable('landlord_ior_couriers')) {
            Schema::create('landlord_ior_couriers', function (Blueprint $table) {
                $table->id();
                $table->string('name');              // e.g. Pathao, DHL Express, FedEx
                $table->string('code')->unique();    // e.g. pathao, dhl, fedex
                $table->string('type');              // domestic, international
                $table->string('region_type');       // country, continent, global
                $table->string('country_code', 3)->nullable()->index(); // e.g. BD, US
                $table->string('region_name')->nullable();             // e.g. Middle East, EU
                
                // Capabilities
                $table->boolean('has_tracking')->default(true);
                $table->boolean('has_booking')->default(false);
                $table->boolean('is_active')->default(true);
                
                // Meta and Docs
                $table->string('api_docs_url')->nullable();
                $table->json('supported_services')->nullable(); // e.g. ["Next Day", "Standard"]
                $table->text('description')->nullable();
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_ior_couriers');
    }
};
