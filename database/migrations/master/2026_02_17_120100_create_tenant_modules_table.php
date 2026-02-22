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
        Schema::connection('mysql')->create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('module_id');
            $table->enum('status', ['active', 'inactive', 'trial', 'expired'])->default('active');
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // For trials/subscriptions
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            
            // Unique constraint - tenant can't subscribe to same module twice
            $table->unique(['tenant_id', 'module_id']);
            
            // Indexes
            $table->index('tenant_id');
            $table->index('module_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('tenant_modules');
    }
};
