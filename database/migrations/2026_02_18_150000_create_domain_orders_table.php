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
        Schema::create('domain_orders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('domain')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->integer('registration_years')->default(1);
            $table->timestamp('expiry_date')->nullable();
            $table->json('registrar_data')->nullable(); // Store response from registrar API
            $table->timestamps();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_orders');
    }
};
