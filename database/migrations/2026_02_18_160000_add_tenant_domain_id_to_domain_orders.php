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
            $table->unsignedBigInteger('tenant_domain_id')->nullable()->after('tenant_id');
            $table->foreign('tenant_domain_id')->references('id')->on('tenant_domains')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domain_orders', function (Blueprint $table) {
            $table->dropForeign(['tenant_domain_id']);
            $table->dropColumn('tenant_domain_id');
        });
    }
};
