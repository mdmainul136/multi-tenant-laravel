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
        // 1. Add versioning to tenant_modules
        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            if (!Schema::connection('mysql')->hasColumn('tenant_modules', 'module_version')) {
                $table->string('module_version', 20)->default('1.0.0')->after('module_id');
            }
        });

        // 2. Add observability to tenant_database_stats
        Schema::connection('mysql')->table('tenant_database_stats', function (Blueprint $table) {
            if (!Schema::connection('mysql')->hasColumn('tenant_database_stats', 'slow_query_count')) {
                $table->unsignedInteger('slow_query_count')->default(0)->after('largest_table_size_mb');
                $table->unsignedBigInteger('write_operation_count')->default(0)->after('slow_query_count');
                $table->json('top_tables_by_growth')->nullable()->after('write_operation_count');
            }
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('tenant_database_stats', function (Blueprint $table) {
            $table->dropColumn(['slow_query_count', 'write_operation_count', 'top_tables_by_growth']);
        });

        Schema::connection('mysql')->table('tenant_modules', function (Blueprint $table) {
            $table->dropColumn('module_version');
        });
    }
};
