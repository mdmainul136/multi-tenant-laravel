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
        Schema::create('landlord_ior_api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // zonos, hurricane, openrouter, oxygen
            $table->string('api_key');
            $table->string('api_secret')->nullable();
            $table->json('supported_regions')->nullable(); // ['US', 'GB', 'IN', 'BD']
            $table->decimal('cost_per_lookup', 10, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_ior_api_configs');
    }
};
