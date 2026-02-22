<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // IOR app settings (key-value store)
        Schema::create('ior_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general'); // payment, shipping, email, general
            $table->timestamps();
        });

        // Customs rates by product category
        Schema::create('ior_customs_rates', function (Blueprint $table) {
            $table->id();
            $table->string('category')->unique(); // cosmetics, electronics, clothing, footwear, etc.
            $table->decimal('rate_percentage', 5, 2)->default(25.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // International shipping settings
        Schema::create('ior_shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shipping_method')->unique(); // air, sea
            $table->decimal('rate_per_kg', 10, 2)->default(1500.00); // BDT per kg
            $table->decimal('min_charge', 10, 2)->default(500.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Courier configurations (Pathao, Steadfast, RedX, FedEx, DHL)
        Schema::create('ior_courier_configs', function (Blueprint $table) {
            $table->id();
            $table->string('courier_code')->unique(); // pathao, steadfast, redx, fedex, dhl
            $table->string('display_name');
            $table->string('type')->default('domestic'); // domestic, international
            $table->json('credentials')->nullable(); // API keys, merchant ID etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed defaults
        DB::table('ior_settings')->insert([
            ['key' => 'default_exchange_rate',    'value' => '120',       'group' => 'pricing', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_profit_margin',    'value' => '20',        'group' => 'pricing', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'advance_payment_percent',  'value' => '50',        'group' => 'pricing', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'admin_notification_email', 'value' => '',          'group' => 'email',   'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bkash_app_key',            'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bkash_app_secret',         'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bkash_username',           'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bkash_password',           'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'bkash_sandbox',            'value' => '1',         'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sslcommerz_store_id',      'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sslcommerz_store_pass',    'value' => '',          'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sslcommerz_sandbox',       'value' => '1',         'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'apify_api_token',          'value' => '',          'group' => 'scraper', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_name',               'value' => 'IOR Store', 'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_address',            'value' => '',          'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_email',            'value' => '',          'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_phone',            'value' => '',          'group' => 'general', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ior_customs_rates')->insert([
            ['category' => 'electronics',  'rate_percentage' => 25.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'clothing',     'rate_percentage' => 37.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'footwear',     'rate_percentage' => 45.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'cosmetics',    'rate_percentage' => 40.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'toys',         'rate_percentage' => 25.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'sports',       'rate_percentage' => 20.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'accessories',  'rate_percentage' => 30.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'general',      'rate_percentage' => 25.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ior_shipping_settings')->insert([
            ['shipping_method' => 'air', 'rate_per_kg' => 1500.00, 'min_charge' => 500.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['shipping_method' => 'sea', 'rate_per_kg' => 400.00,  'min_charge' => 200.00, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ior_courier_configs')->insert([
            ['courier_code' => 'pathao',    'display_name' => 'Pathao',    'type' => 'domestic',      'credentials' => json_encode(['client_id' => '', 'client_secret' => '']), 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['courier_code' => 'steadfast', 'display_name' => 'Steadfast', 'type' => 'domestic',      'credentials' => json_encode(['api_key' => '', 'secret_key' => '']),      'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['courier_code' => 'redx',      'display_name' => 'RedX',      'type' => 'domestic',      'credentials' => json_encode(['api_key' => '']),                           'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['courier_code' => 'fedex',     'display_name' => 'FedEx',     'type' => 'international', 'credentials' => json_encode(['api_key' => '', 'api_secret' => '', 'account_number' => '']), 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['courier_code' => 'dhl',       'display_name' => 'DHL',       'type' => 'international', 'credentials' => json_encode(['api_key' => '', 'account_number' => '']),  'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ior_courier_configs');
        Schema::dropIfExists('ior_shipping_settings');
        Schema::dropIfExists('ior_customs_rates');
        Schema::dropIfExists('ior_settings');
    }
};
