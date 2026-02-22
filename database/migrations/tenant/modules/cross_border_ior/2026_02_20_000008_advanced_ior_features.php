<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table to track price changes for products (useful for IOR due to FX changes)
        Schema::create('ec_product_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('old_price', 15, 2);
            $table->decimal('new_price', 15, 2);
            $table->decimal('old_cost', 15, 2)->nullable(); // Useful for IOR (USD cost)
            $table->decimal('new_cost', 15, 2)->nullable();
            $table->string('reason')->nullable(); // FX Change, Manual, Promo, etc.
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('ec_products')->onDelete('cascade');
        });

        // Add index to ior_logs to make timeline fetching faster
        Schema::table('ior_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ior_logs', 'visible_to_customer')) {
                $table->boolean('visible_to_customer')->default(true)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_product_price_history');
        Schema::table('ior_logs', function (Blueprint $table) {
            $table->dropColumn('visible_to_customer');
        });
    }
};
