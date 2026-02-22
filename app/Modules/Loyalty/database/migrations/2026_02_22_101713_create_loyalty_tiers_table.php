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
        Schema::dropIfExists('loyalty_tiers');
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('name'); // Bronze, Silver, Gold
            $table->integer('min_points'); // Points required to enter this tier
            $table->decimal('multiplier', 4, 2)->default(1.0); // 1.1x points for Silver, etc.
            $table->json('benefits')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
