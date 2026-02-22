<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ior_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ior_foreign_orders')->cascadeOnDelete();
            $table->string('transaction_id')->nullable();         // gateway-specific tx ID
            $table->string('gateway');                            // bkash, sslcommerz, stripe, cod
            $table->string('payment_type')->default('advance');   // advance, remaining, full
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('BDT');
            $table->string('status')->default('initiated');       // initiated, pending, paid, failed, refunded
            // bKash-specific
            $table->string('bkash_payment_id')->nullable();
            $table->string('bkash_trx_id')->nullable();
            // SSLCommerz-specific
            $table->string('val_id')->nullable();
            $table->string('bank_transaction_id')->nullable();
            $table->string('card_type')->nullable();
            // Stripe-specific
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent')->nullable();
            // Raw response
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['gateway', 'status']);
        });

        Schema::create('ior_import_logs', function (Blueprint $table) {
            $table->id();
            $table->text('product_url');
            $table->string('marketplace')->nullable();    // amazon, ebay, walmart, alibaba
            $table->string('scraper')->nullable();        // apify, oxylabs
            $table->string('status');                     // success, failed, partial
            $table->text('error_message')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('ior_foreign_orders')->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('duration_ms')->nullable();   // scrape duration
            $table->timestamps();

            $table->index('status');
            $table->index('marketplace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ior_import_logs');
        Schema::dropIfExists('ior_payment_transactions');
    }
};
