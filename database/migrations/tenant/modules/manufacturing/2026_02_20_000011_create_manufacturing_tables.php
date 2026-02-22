<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bill of Materials (BOM) - Defines what goes into a finished product
        Schema::create('ec_bom', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finished_product_id');
            $table->string('name'); // e.g., Standard Production, Premium Variant
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('finished_product_id')->references('id')->on('ec_products')->onDelete('cascade');
        });

        // 2. BOM Items - The raw materials in a BOM
        Schema::create('ec_bom_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('raw_material_id');
            $table->decimal('quantity', 15, 4); // Amount needed for 1 unit of finished product
            $table->timestamps();

            $table->foreign('bom_id')->references('id')->on('ec_bom')->onDelete('cascade');
            $table->foreign('raw_material_id')->references('id')->on('ec_products')->onDelete('cascade');
        });

        // 3. Manufacturing Orders (MO) - Tracking the production process
        Schema::create('ec_manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('finished_product_id');
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->unsignedBigInteger('warehouse_id'); // Where production happens & items are stored
            $table->integer('target_quantity');
            $table->integer('produced_quantity')->default(0);
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('user_id'); // Created by
            $table->timestamps();

            $table->foreign('finished_product_id')->references('id')->on('ec_products')->onDelete('cascade');
            $table->foreign('bom_id')->references('id')->on('ec_bom')->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on('ec_warehouses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_manufacturing_orders');
        Schema::dropIfExists('ec_bom_items');
        Schema::dropIfExists('ec_bom');
    }
};
