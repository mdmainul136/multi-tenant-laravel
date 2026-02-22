<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('database_plan_id')
                  ->nullable()
                  ->after('status')
                  ->constrained('tenant_database_plans')
                  ->nullOnDelete();
            $table->string('db_username')->nullable()->after('database_plan_id');
            $table->text('db_password_encrypted')->nullable()->after('db_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['database_plan_id']);
            $table->dropColumn(['database_plan_id', 'db_username', 'db_password_encrypted']);
        });
    }
};
