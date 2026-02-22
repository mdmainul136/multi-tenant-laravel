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
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            // Add new columns for enhanced subscription management
            $table->enum('subscription_type', ['trial', 'monthly', 'annual', 'lifetime'])->default('monthly')->after('module_id');
            $table->decimal('price_paid', 10, 2)->nullable()->after('subscription_type');
            $table->timestamp('starts_at')->nullable()->after('subscribed_at');
            $table->boolean('auto_renew')->default(true)->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('auto_renew');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null')->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn([
                'subscription_type',
                'price_paid',
                'starts_at',
                'auto_renew',
                'cancelled_at',
                'payment_id'
            ]);
        });
    }
};
