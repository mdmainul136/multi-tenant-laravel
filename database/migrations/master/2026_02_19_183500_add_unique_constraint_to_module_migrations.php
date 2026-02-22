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
        Schema::connection('mysql')->table('module_migrations', function (Blueprint $table) {
            // Add unique constraint to prevent race conditions
            $table->unique(
                ['tenant_database', 'module_key', 'migration_file'], 
                'tenant_module_migration_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('module_migrations', function (Blueprint $table) {
            $table->dropUnique('tenant_module_migration_unique');
        });
    }
};
