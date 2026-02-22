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
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('domain');
        });

        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->index('is_primary');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['domain']);
        });

        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropIndex(['is_primary']);
            $table->dropIndex(['is_verified']);
        });
    }
};
