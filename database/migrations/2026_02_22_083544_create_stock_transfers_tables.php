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
        Schema::create('ec_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('transfer_number')->unique();
            $table->unsignedBigInteger('from_warehouse_id');
            $table->unsignedBigInteger('to_warehouse_id');
            $table->string('status')->default('pending'); // pending, in_transit, completed, cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->foreign('from_warehouse_id')->references('id')->on('warehouses');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ec_stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 2);
            $table->decimal('received_quantity', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('ec_stock_transfers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_stock_transfer_items');
        Schema::dropIfExists('ec_stock_transfers');
    }
};
