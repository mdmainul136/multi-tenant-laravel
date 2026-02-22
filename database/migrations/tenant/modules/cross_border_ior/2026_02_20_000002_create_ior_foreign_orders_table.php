<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ior_foreign_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->nullable(); // FPO-YYYYMMDD-00001

            // Customer
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();

            // Product
            $table->text('product_url');
            $table->string('product_name');
            $table->integer('quantity')->default(1);
            $table->string('product_variant')->nullable();
            $table->text('product_image_url')->nullable();
            $table->string('source_marketplace')->nullable(); // amazon, alibaba, ebay, walmart, other
            $table->decimal('source_price_usd', 10, 2)->nullable(); // original USD price

            // BDT Pricing breakdown
            $table->decimal('exchange_rate', 10, 4)->nullable();           // BDT per 1 USD at order time
            $table->decimal('base_price_bdt', 10, 2)->nullable();          // source_price × exchange_rate × qty
            $table->decimal('customs_fee_bdt', 10, 2)->nullable();         // customs duty
            $table->decimal('shipping_cost_bdt', 10, 2)->nullable();       // int'l shipping
            $table->decimal('profit_margin_bdt', 10, 2)->nullable();       // margin
            $table->decimal('estimated_price_bdt', 10, 2)->nullable();     // quoted total
            $table->decimal('final_price_bdt', 10, 2)->nullable();         // confirmed total

            // 50/50 payment split
            $table->decimal('advance_amount', 10, 2)->nullable();
            $table->decimal('remaining_amount', 10, 2)->nullable();
            $table->boolean('advance_paid')->default(false);
            $table->boolean('remaining_paid')->default(false);

            // Payment info
            $table->string('payment_method')->nullable();   // bkash, sslcommerz, stripe, cod
            $table->string('payment_status')->default('pending'); // pending, partial, paid

            // Shipping delivery
            $table->string('shipping_full_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_area')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('courier_code')->nullable();    // pathao, steadfast, redx, fedex, dhl

            // Status lifecycle: pending → sourcing → ordered → shipped → customs → delivered → cancelled
            $table->string('order_status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Scrape data cache
            $table->json('scraped_data')->nullable();      // raw scraped product JSON
            $table->json('pricing_breakdown')->nullable(); // pricing_settings for reference

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('order_number');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ior_foreign_orders');
    }
};
