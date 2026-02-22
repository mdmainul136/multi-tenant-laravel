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
        Schema::create('ec_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2); // Percentage or fixed amount
            $table->decimal('min_purchase', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable(); // Max discount for percentage
            $table->integer('max_uses')->nullable(); // Total usage limit
            $table->integer('max_uses_per_customer')->default(1);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index(['starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_coupons');
    }
};
