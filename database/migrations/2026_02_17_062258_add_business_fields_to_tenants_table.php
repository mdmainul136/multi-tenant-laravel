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
            $table->string('company_name')->after('tenant_name');
            $table->enum('business_type', ['sole_proprietorship', 'partnership', 'llc', 'corporation'])->after('company_name');
            $table->string('admin_name')->after('business_type');
            $table->string('phone', 20)->after('admin_email');
            $table->text('address')->after('phone');
            $table->string('city', 100)->after('address');
            $table->string('country', 100)->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'business_type',
                'admin_name',
                'phone',
                'address',
                'city',
                'country'
            ]);
        });
    }
};
