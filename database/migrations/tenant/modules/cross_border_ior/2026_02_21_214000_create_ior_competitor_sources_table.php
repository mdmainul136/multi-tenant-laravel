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
        if (!Schema::hasTable('ior_competitor_sources')) {
            Schema::create('ior_competitor_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('source_url', 1000);
                $table->string('marketplace')->nullable();
                $table->decimal('last_usd_price', 10, 2)->nullable();
                $table->string('stock_status')->default('unknown');
                $table->boolean('is_primary')->default(false);
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_competitor_sources');
    }
};
