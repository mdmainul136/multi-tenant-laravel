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
        Schema::create('tenant_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('provider')->default('gemini'); // gemini, claude, openai
            $table->text('api_key')->nullable(); // Encrypted in model
            $table->string('model_name')->nullable();
            $table->boolean('use_platform_credits')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_ai_settings');
    }
};
