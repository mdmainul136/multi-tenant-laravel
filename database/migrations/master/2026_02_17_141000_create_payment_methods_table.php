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
        Schema::connection('mysql')->create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            $table->string('stripe_payment_method_id')->unique();
            $table->string('type')->default('card'); // card, bank_account
            $table->string('brand')->nullable(); // visa, mastercard, amex
            $table->string('last4'); // Last 4 digits
            $table->integer('exp_month')->nullable();
            $table->integer('exp_year')->nullable();
            
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('payment_methods');
    }
};
