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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plan_key')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->json('quotas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
