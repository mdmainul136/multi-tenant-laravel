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
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            if (!Schema::connection('mysql')->hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::connection('mysql')->hasColumn('payments', 'payment_gateway_response')) {
                $table->json('payment_gateway_response')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_gateway_response']);
        });
    }
};
