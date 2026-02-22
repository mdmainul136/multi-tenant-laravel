<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('ec_suppliers')->onDelete('restrict');
            $table->string('status')->default('draft')->comment('draft/sent/partial/received/cancelled');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('shipping', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_purchase_orders');
    }
};
