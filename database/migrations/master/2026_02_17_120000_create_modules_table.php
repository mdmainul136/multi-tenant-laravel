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
        Schema::connection('mysql')->create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique(); // pos, ecommerce, crm
            $table->string('module_name'); // Display name
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_active')->default(true); // Can be subscribed
            $table->decimal('price', 10, 2)->default(0.00); // Monthly price
            $table->timestamps();
            
            $table->index('module_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('modules');
    }
};
