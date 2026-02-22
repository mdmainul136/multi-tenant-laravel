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
        if (!Schema::hasTable('ior_price_history')) {
            Schema::create('ior_price_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('price_usd', 10, 2);
                $table->string('stock_status')->default('in_stock');
                $table->timestamp('recorded_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_price_history');
    }
};
