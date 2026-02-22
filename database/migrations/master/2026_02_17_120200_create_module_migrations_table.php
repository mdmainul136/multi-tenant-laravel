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
        Schema::connection('mysql')->create('module_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_database'); // Which tenant DB
            $table->string('module_key'); // Which module
            $table->string('migration_file'); // Migration filename
            $table->integer('batch');
            $table->timestamps();
            
            // Indexes
            $table->index('tenant_database');
            $table->index('module_key');
            $table->index(['tenant_database', 'module_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('module_migrations');
    }
};
