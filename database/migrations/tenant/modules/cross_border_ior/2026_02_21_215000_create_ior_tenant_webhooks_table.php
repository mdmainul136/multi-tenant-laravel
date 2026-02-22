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
        if (!Schema::hasTable('ior_tenant_webhooks')) {
            Schema::create('ior_tenant_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('endpoint_url');
                $table->string('secret_token')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('events')->nullable(); // ['price_change', 'out_of_stock', 'removed']
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_tenant_webhooks');
    }
};
