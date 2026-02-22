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
        Schema::dropIfExists('loyalty_programs');
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->decimal('points_per_currency', 8, 2)->default(1); // e.g., 1 point per 1 unit
            $table->decimal('currency_per_point', 8, 2)->default(0.1); // e.g., 1 point = 0.1 units
            $table->integer('min_redemption_points')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
