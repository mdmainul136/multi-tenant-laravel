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
        if (!Schema::hasTable('ior_hs_lookup_logs')) {
            Schema::create('ior_hs_lookup_logs', function (Blueprint $table) {
                $table->id();
                $table->string('hs_code', 20)->index();
                $table->string('destination_country', 3)->index(); // ISO 2-3 char
                $table->decimal('cost_usd', 10, 4)->default(0);
                $table->string('source')->nullable(); // zonos, internal, etc.
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                // Index for caching lookup (same HS + Country for tenant)
                $table->index(['hs_code', 'destination_country', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_hs_lookup_logs');
    }
};
