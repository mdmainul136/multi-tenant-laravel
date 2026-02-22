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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('tenant_name');
            $table->string('database_name');
            $table->string('admin_email');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
