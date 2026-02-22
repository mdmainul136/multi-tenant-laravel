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
        Schema::create('ior_product_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->string('source_url', 1000);
            $table->string('marketplace')->nullable();
            $table->string('stock_status')->default('unknown');
            $table->decimal('last_usd_price', 10, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('checksum_hash')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('ec_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_product_sources');
    }
};
