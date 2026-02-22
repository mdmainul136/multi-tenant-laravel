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
        // 1. Multi-tenant Scraper Settings
        if (!Schema::hasTable('ior_scraper_settings')) {
            Schema::create('ior_scraper_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->decimal('monthly_budget_cap', 10, 2)->default(100.00);
                $table->decimal('current_monthly_spend', 10, 4)->default(0);
                $table->integer('rate_limit_per_minute')->default(10);
                $table->boolean('is_active')->default(true);
                $table->json('allowed_marketplaces')->nullable();
                $table->timestamps();
            });
        }

        // 2. Product Variants (Size/Color/Options)
        if (!Schema::hasTable('ior_product_variants')) {
            Schema::create('ior_product_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('variant_key')->index();
                $table->json('attributes');
                $table->decimal('price_usd', 10, 2)->nullable();
                $table->string('stock_status')->default('in_stock');
                $table->string('sku_suffix')->nullable();
                $table->timestamps();
            });
        }

        // 3. Scraper Health Monitoring
        if (!Schema::hasTable('ior_scraper_health_stats')) {
            Schema::create('ior_scraper_health_stats', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace')->unique();
                $table->integer('success_count')->default(0);
                $table->integer('failure_count')->default(0);
                $table->integer('blocked_count')->default(0);
                $table->integer('avg_duration_ms')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_scraper_settings');
        Schema::dropIfExists('ior_product_variants');
        Schema::dropIfExists('ior_scraper_health_stats');
    }
};
