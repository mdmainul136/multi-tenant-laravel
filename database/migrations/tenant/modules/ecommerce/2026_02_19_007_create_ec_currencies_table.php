<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('USD, BDT, EUR, GBP, INR etc.');
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('exchange_rate_to_usd', 20, 8)->default(1.00000000)
                  ->comment('1 currency unit = X USD');
            $table->boolean('is_default')->default(false)
                  ->comment('Only one currency can be default');
            $table->boolean('is_active')->default(true);
            $table->timestamp('rates_updated_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_default');
        });

        // Seed common currencies
        DB::connection('tenant_dynamic')->table('ec_currencies')->insert([
            ['code' => 'USD', 'name' => 'US Dollar',         'symbol' => '$',  'exchange_rate_to_usd' => 1.00,    'is_default' => true,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka',  'symbol' => '৳',  'exchange_rate_to_usd' => 0.0091,  'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro',               'symbol' => '€',  'exchange_rate_to_usd' => 1.08,   'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GBP', 'name' => 'British Pound',      'symbol' => '£',  'exchange_rate_to_usd' => 1.27,   'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'INR', 'name' => 'Indian Rupee',       'symbol' => '₹',  'exchange_rate_to_usd' => 0.012,  'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_currencies');
    }
};
