<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Warehouses Table
        Schema::create('ec_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 2. Warehouse Inventory (Pivot table for multi-warehouse stock)
        Schema::create('ec_warehouse_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(0);
            $table->integer('alert_quantity')->default(5); // Low stock alert threshold
            $table->string('bin_location')->nullable(); // Specific shelf/bin in warehouse
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id']);
            $table->foreign('warehouse_id')->references('id')->on('ec_warehouses')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('ec_products')->onDelete('cascade');
        });

        // 3. Stock Logs / Adjustments
        Schema::create('ec_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->integer('change'); // e.g. +10 or -5
            $table->integer('balance_after');
            $table->enum('type', ['initial', 'purchase', 'sale', 'transfer_in', 'transfer_out', 'adjustment', 'return']);
            $table->string('reference_type')->nullable(); // Order, PO, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Who made the adjustment
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('ec_products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('ec_warehouses')->onDelete('cascade');
        });

        // Adjust ec_products to reflect global stock (aggregated or main)
        Schema::table('ec_products', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(5)->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
        Schema::dropIfExists('ec_stock_logs');
        Schema::dropIfExists('ec_warehouse_inventory');
        Schema::dropIfExists('ec_warehouses');
    }
};
