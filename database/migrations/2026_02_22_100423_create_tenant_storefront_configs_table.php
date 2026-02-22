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
        Schema::create('tenant_storefront_configs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('theme_id')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->json('config_json');
            $table->string('version')->default('1.0.0');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('rollback_from')->nullable();
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('themes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_storefront_configs');
    }
};
