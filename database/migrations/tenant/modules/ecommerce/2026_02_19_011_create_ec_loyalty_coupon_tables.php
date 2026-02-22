<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // Global loyalty program config (one per tenant)
        Schema::connection($this->connection)->create('ec_loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Loyalty Rewards');
            $table->decimal('points_per_currency_unit', 10, 4)->default(1.00)
                  ->comment('Points earned per 1 base currency unit spent');
            $table->unsignedInteger('min_redeem_points')->default(100)
                  ->comment('Minimum points required to redeem');
            $table->decimal('point_value', 10, 4)->default(0.01)
                  ->comment('1 point = X base currency');
            $table->unsignedInteger('points_expiry_days')->default(0)
                  ->comment('0 = never expire');
            $table->boolean('is_active')->default(true);
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        // Per-customer points balance
        Schema::connection($this->connection)->create('ec_customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('ec_customers')->onDelete('cascade');
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_earned')->default(0);
            $table->integer('lifetime_redeemed')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('points_balance');
        });

        // Points transaction ledger
        Schema::connection($this->connection)->create('ec_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('ec_customers')->onDelete('cascade');
            $table->integer('points')->comment('Positive = earned, Negative = redeemed');
            $table->string('type')->comment('earn/redeem/adjust/expire');
            $table->string('reference_type')->nullable()->comment('order/manual/coupon');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('balance_after');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('type');
            $table->index('created_at');
        });

        // Coupons
        Schema::connection($this->connection)->create('ec_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->comment('fixed/percent/free_shipping/buy_x_get_y');
            $table->decimal('value', 15, 2)->default(0)
                  ->comment('Discount amount or percentage');
            $table->decimal('max_discount', 15, 2)->nullable()
                  ->comment('Max discount cap for percent type');
            $table->decimal('min_order_amount', 15, 2)->default(0);
            $table->unsignedInteger('max_uses')->default(0)->comment('0 = unlimited');
            $table->unsignedInteger('max_uses_per_customer')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('applies_to_all')->default(true);
            $table->string('applies_to_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('expires_at');
        });

        // Coupon usage tracking
        Schema::connection($this->connection)->create('ec_coupon_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('ec_coupons')->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('discount_amount', 15, 2);
            $table->timestamps();

            $table->index('coupon_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_coupon_uses');
        Schema::connection($this->connection)->dropIfExists('ec_coupons');
        Schema::connection($this->connection)->dropIfExists('ec_loyalty_transactions');
        Schema::connection($this->connection)->dropIfExists('ec_customer_points');
        Schema::connection($this->connection)->dropIfExists('ec_loyalty_programs');
    }
};
