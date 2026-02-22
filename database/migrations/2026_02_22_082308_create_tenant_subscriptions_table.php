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
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            $table->string('status')->default('active'); // active, trialing, past_due, canceled, expired
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
