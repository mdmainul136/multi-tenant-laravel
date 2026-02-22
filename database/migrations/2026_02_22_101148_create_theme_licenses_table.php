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
        Schema::create('theme_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('theme_id')->constrained()->onDelete('cascade');
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('revenue_split_amount', 10, 2); // Amount going to developer
            $table->string('license_status')->default('active'); // active, revoked, expired
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'theme_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_licenses');
    }
};
