<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_database_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // "Starter", "Business", "Enterprise"
            $table->string('slug')->unique();    // "starter", "business", "enterprise"
            $table->unsignedInteger('storage_limit_gb'); // 10, 15, 20
            $table->unsignedInteger('max_tables')->nullable(); // null = unlimited
            $table->unsignedInteger('max_connections')->default(10);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_database_plans');
    }
};
