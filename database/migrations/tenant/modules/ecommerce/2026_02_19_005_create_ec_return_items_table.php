<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('ec_returns')->onDelete('cascade');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->string('condition')->default('used')->comment('new/used/damaged/defective');
            $table->boolean('restocked')->default(false);
            $table->timestamps();

            $table->index('return_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_return_items');
    }
};
