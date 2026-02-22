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
        Schema::dropIfExists('loyalty_points');
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->integer('points'); // Positive for earn, negative for redeem
            $table->string('transaction_type'); // earn, redeem, adjustment, refund
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_points');
    }
};
