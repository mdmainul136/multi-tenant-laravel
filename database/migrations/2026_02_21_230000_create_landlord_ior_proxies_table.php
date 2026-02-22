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
        if (!Schema::hasTable('landlord_ior_proxies')) {
            Schema::create('landlord_ior_proxies', function (Blueprint $table) {
                $table->id();
                $table->string('provider'); // Oxylabs, BrightData, etc.
                $table->string('proxy_type')->default('residential'); // residential, mobile, datacenter
                $table->string('host');
                $table->integer('port');
                $table->string('username')->nullable();
                $table->string('password')->nullable();
                
                // Health & Fail-Scoring
                $table->integer('fail_count')->default(0);
                $table->integer('success_count')->default(0);
                $table->integer('score')->default(100); // 0-100 quality score
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('last_failed_at')->nullable();
                
                // Metadata
                $table->string('country_code', 3)->nullable(); // Filter by country
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable(); // For cost per GB, etc.
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_ior_proxies');
    }
};
