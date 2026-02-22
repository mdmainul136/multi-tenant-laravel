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
        Schema::table('ior_product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('ior_product_variants', 'image_url')) {
                $table->string('image_url', 1000)->nullable()->after('attributes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ior_product_variants', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
