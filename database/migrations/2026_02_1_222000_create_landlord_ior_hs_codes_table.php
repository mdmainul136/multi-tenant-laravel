<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations on the LANDLORD (MASTER) database.
     */
    public function up(): void
    {
        if (!Schema::hasTable('landlord_ior_hs_codes')) {
            Schema::create('landlord_ior_hs_codes', function (Blueprint $table) {
                $table->id();
                $table->string('hs_code', 20)->index();
                $table->string('country_code', 3)->default('BGD')->index(); // ISO 3166-1 alpha-3
                $table->string('category_en');
                $table->string('category_bn')->nullable();
                
                // Cascading Tax Percentages
                $table->decimal('cd', 5, 2)->default(0);  // Customs Duty
                $table->decimal('rd', 5, 2)->default(0);  // Regulatory Duty
                $table->decimal('sd', 5, 2)->default(0);  // Supplementary Duty
                $table->decimal('vat', 5, 2)->default(15); // Value Added Tax
                $table->decimal('ait', 5, 2)->default(5);  // Advance Income Tax
                $table->decimal('at', 5, 2)->default(5);   // Advance Tax
                
                $table->boolean('is_restricted')->default(false);
                $table->string('restriction_reason')->nullable();
                
                $table->timestamps();

                $table->unique(['hs_code', 'country_code']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_ior_hs_codes');
    }
};
