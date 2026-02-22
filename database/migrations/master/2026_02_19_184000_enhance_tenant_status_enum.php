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
        Schema::table('tenants', function (Blueprint $table) {
            // Update enum to include production-ready statuses
            $table->enum('status', ['active', 'suspended', 'billing_failed', 'terminated'])
                ->default('active')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->change();
        });
    }
};
