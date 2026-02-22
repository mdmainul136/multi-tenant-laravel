<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('ec_purchase_orders')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_purchase_order_items');
    }
};
