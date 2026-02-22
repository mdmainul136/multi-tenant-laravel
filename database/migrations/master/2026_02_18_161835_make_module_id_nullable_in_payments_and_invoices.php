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
            $table->foreignId('module_id')->nullable()->change();
        });

        Schema::connection('mysql')->table('invoices', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('payments', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable(false)->change();
        });

        Schema::connection('mysql')->table('invoices', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable(false)->change();
        });
    }
};
