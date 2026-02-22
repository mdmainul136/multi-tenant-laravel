<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 006 — IOR Supporting Tables
 *
 * Creates:
 *   - ior_logs               : General event / email audit log
 *   - ior_exchange_rate_logs : Exchange rate history (used by BulkPriceRecalculator)
 *   - catalog_products       : Foreign product catalogue (scraped products)
 *
 * Also adds missing columns to ior_foreign_orders:
 *   - product_category, product_features, product_specs,
 *     product_weight_kg, product_url (already exists, guarded),
 *     notes, tracking_url, courier_order_id, shipped_at, delivered_at
 *
 * And seeds new ior_settings entries for Nagad + Apify + Oxylabs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────
        // 1. IOR GENERAL EVENT LOG
        // ─────────────────────────────────────────────────────────────
        Schema::create('ior_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('event', 100);                    // email_confirmed, price_recalculated, courier_booked …
            $table->text('payload')->nullable();             // JSON blob (to, subject, tracking_number …)
            $table->string('status', 30)->default('ok');    // ok | sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'event']);
            $table->index('created_at');
        });

        // ─────────────────────────────────────────────────────────────
        // 2. EXCHANGE RATE HISTORY LOG
        // ─────────────────────────────────────────────────────────────
        Schema::create('ior_exchange_rate_logs', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate', 10, 4);                  // BDT per 1 USD
            $table->string('source', 60)->default('api');    // api | manual | bulk_recalculate | cron
            $table->integer('orders_updated')->default(0);
            $table->json('meta')->nullable();                // extra info (previous_rate, provider, …)
            $table->timestamps();

            $table->index('created_at');
        });

        // ─────────────────────────────────────────────────────────────
        // 3. CATALOG PRODUCTS (foreign product store)
        // ─────────────────────────────────────────────────────────────
        if (!Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->text('short_description')->nullable();

                // Pricing
                $table->decimal('price', 12, 2)->nullable();        // Source price (USD)
                $table->decimal('price_bdt', 12, 2)->nullable();    // Calculated BDT price
                $table->string('currency', 10)->default('USD');

                // Media
                $table->text('thumbnail_url')->nullable();
                $table->json('images')->nullable();                  // array of image URLs

                // Product metadata
                $table->string('brand')->nullable();
                $table->string('sku')->nullable();
                $table->string('availability')->default('unknown'); // in_stock | out_of_stock | unknown
                $table->string('status')->default('draft');         // draft | published | archived
                $table->string('product_type')->default('foreign'); // foreign | local

                // Marketplace info (stored in attributes JSON)
                $table->json('attributes')->nullable();             // {original_url, marketplace, features, last_synced_at, …}

                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('product_type');
                $table->index('availability');
                $table->index('created_at');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 4. ADD MISSING COLUMNS TO ior_foreign_orders
        // ─────────────────────────────────────────────────────────────
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            // Product detail columns used by services
            if (!Schema::hasColumn('ior_foreign_orders', 'product_category')) {
                $table->string('product_category')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'product_weight_kg')) {
                $table->decimal('product_weight_kg', 8, 3)->nullable()->default(0.5)->after('product_category');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'product_features')) {
                $table->json('product_features')->nullable()->after('product_weight_kg');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'product_specs')) {
                $table->json('product_specs')->nullable()->after('product_features');
            }

            // Shipping / fulfillment
            if (!Schema::hasColumn('ior_foreign_orders', 'shipping_postal_code')) {
                $table->string('shipping_postal_code')->nullable()->after('shipping_city');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'courier_order_id')) {
                $table->string('courier_order_id')->nullable()->after('courier_code');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'tracking_url')) {
                $table->text('tracking_url')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'courier_label_url')) {
                $table->text('courier_label_url')->nullable()->after('tracking_url');
            }

            // Status timestamps
            if (!Schema::hasColumn('ior_foreign_orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('admin_note');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
            if (!Schema::hasColumn('ior_foreign_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }

            // Customer notes
            if (!Schema::hasColumn('ior_foreign_orders', 'notes')) {
                $table->text('notes')->nullable()->after('admin_note');
            }
        });

        // ─────────────────────────────────────────────────────────────
        // 5. NEW ior_settings seeds (Nagad, Oxylabs, Apify extras)
        // ─────────────────────────────────────────────────────────────
        $newSettings = [
            // Nagad payment
            ['key' => 'nagad_merchant_id',  'value' => '', 'group' => 'payment'],
            ['key' => 'nagad_merchant_key', 'value' => '', 'group' => 'payment'],
            ['key' => 'nagad_private_key',  'value' => '', 'group' => 'payment'],
            ['key' => 'nagad_sandbox',      'value' => '1','group' => 'payment'],

            // Oxylabs scraping
            ['key' => 'oxylabs_username',   'value' => '', 'group' => 'scraper'],
            ['key' => 'oxylabs_password',   'value' => '', 'group' => 'scraper'],

            // Apify extras
            ['key' => 'apify_active',               'value' => '0',   'group' => 'scraper'],
            ['key' => 'apify_fallback_to_oxylabs',  'value' => '1',   'group' => 'scraper'],
            ['key' => 'apify_bestseller_actor',     'value' => 'curious_coder~amazon-scraper', 'group' => 'scraper'],

            // Image AI (vision)
            ['key' => 'ai_provider',        'value' => 'openai', 'group' => 'ai'],
            ['key' => 'default_ai_model',   'value' => 'gpt-4o', 'group' => 'ai'],
            ['key' => 'google_api_key',     'value' => '',        'group' => 'ai'],
        ];

        foreach ($newSettings as $setting) {
            \DB::table('ior_settings')->insertOrIgnore([
                'key'        => $setting['key'],
                'value'      => $setting['value'],
                'group'      => $setting['group'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove added columns from ior_foreign_orders
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                'product_category', 'product_weight_kg', 'product_features', 'product_specs',
                'shipping_postal_code', 'courier_order_id', 'tracking_url', 'courier_label_url',
                'shipped_at', 'delivered_at', 'cancelled_at', 'notes',
            ], fn($col) => Schema::hasColumn('ior_foreign_orders', $col)));
        });

        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('ior_exchange_rate_logs');
        Schema::dropIfExists('ior_logs');

        \DB::table('ior_settings')->whereIn('key', [
            'nagad_merchant_id', 'nagad_merchant_key', 'nagad_private_key', 'nagad_sandbox',
            'oxylabs_username', 'oxylabs_password',
            'apify_active', 'apify_fallback_to_oxylabs', 'apify_bestseller_actor',
            'ai_provider', 'default_ai_model', 'google_api_key',
        ])->delete();
    }
};
