<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained('ec_orders')->onDelete('restrict');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status')->default('requested')
                  ->comment('requested/approved/processing/refunded/rejected/exchanged');
            $table->string('type')->default('refund')->comment('refund/exchange');
            $table->string('reason');
            $table->text('reason_detail')->nullable();
            $table->string('refund_method')->nullable()
                  ->comment('original_payment/store_credit/bank_transfer/cash');
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->boolean('restock_items')->default(true);
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_returns');
    }
};
