<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Wallets Table
        Schema::create('ec_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->unique();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('BDT');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('ec_customers')->onDelete('cascade');
        });

        // 2. Wallet Transactions Table
        Schema::create('ec_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'refund', 'bonus']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable(); // Order, Refund, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('ec_wallets')->onDelete('cascade');
        });

        // 3. Refunds Table
        Schema::create('ec_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])->default('pending');
            $table->enum('refund_method', ['wallet', 'original_method'])->default('wallet');
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('ec_orders')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('ec_customers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ec_refunds');
        Schema::dropIfExists('ec_wallet_transactions');
        Schema::dropIfExists('ec_wallets');
    }
};
