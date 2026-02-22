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
        // Table to log every scrape attempt for billing and audit
        Schema::create('ior_scraper_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // python, apify, oxylabs
            $table->string('marketplace'); // amazon, walmart, etc.
            $table->string('source_url', 1000);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('status'); // success, failed
            $table->decimal('cost', 10, 4)->default(0); // Cost in USD/Credits
            $table->json('response_summary')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });

        // Add restock alert support to product sources
        Schema::table('ior_product_sources', function (Blueprint $table) {
            $table->boolean('restock_alert_enabled')->default(false)->after('stock_status');
            $table->timestamp('last_restocked_at')->nullable()->after('restock_alert_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_scraper_logs');
        Schema::table('ior_product_sources', function (Blueprint $table) {
            $table->dropColumn(['restock_alert_enabled', 'last_restocked_at']);
        });
    }
};
