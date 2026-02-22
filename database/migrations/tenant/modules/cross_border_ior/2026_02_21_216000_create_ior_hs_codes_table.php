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
        if (!Schema::hasTable('ior_hs_codes')) {
            Schema::create('ior_hs_codes', function (Blueprint $table) {
                $table->id();
                $table->string('hs_code', 20)->unique()->index();
                $table->string('category_en');
                $table->string('category_bn')->nullable();
                
                // Duty layers in percentages (e.g., 25.00 for 25%)
                $table->decimal('cd', 5, 2)->default(0);  // Customs Duty
                $table->decimal('rd', 5, 2)->default(0);  // Regulatory Duty
                $table->decimal('sd', 5, 2)->default(0);  // Supplementary Duty
                $table->decimal('vat', 5, 2)->default(15); // Value Added Tax (Default 15% in BD)
                $table->decimal('ait', 5, 2)->default(5);  // Advance Income Tax
                $table->decimal('at', 5, 2)->default(5);   // Advance Tax
                
                $table->boolean('is_restricted')->default(false);
                $table->string('restriction_note')->nullable();
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_hs_codes');
    }
};
