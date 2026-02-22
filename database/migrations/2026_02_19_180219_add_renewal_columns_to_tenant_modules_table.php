<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            // Add all columns the TenantModule model uses but DB doesn't have yet
            $table->boolean('auto_renew')->default(true)->after('expires_at');
            $table->decimal('price_paid', 10, 2)->nullable()->after('auto_renew');
            $table->timestamp('starts_at')->nullable()->after('price_paid');
            $table->unsignedBigInteger('payment_id')->nullable()->after('starts_at');

            // Auto-renewal tracking
            $table->timestamp('notified_renewal_failed_at')->nullable()->after('payment_id');
            $table->timestamp('last_renewed_at')->nullable()->after('notified_renewal_failed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn([
                'auto_renew', 'price_paid', 'starts_at', 'payment_id',
                'notified_renewal_failed_at', 'last_renewed_at',
            ]);
        });
    }
};
