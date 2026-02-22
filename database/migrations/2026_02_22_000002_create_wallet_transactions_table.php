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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->enum('type', ['credit', 'debit']);
            $table->string('service_type'); // scraper, ai, customs_lookup, topup
            $table->decimal('amount', 15, 4);
            $table->decimal('balance_before', 15, 4);
            $table->decimal('balance_after', 15, 4);
            $table->string('description')->nullable();
            $table->string('reference_id')->nullable(); // Order ID or Scrape ID
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
