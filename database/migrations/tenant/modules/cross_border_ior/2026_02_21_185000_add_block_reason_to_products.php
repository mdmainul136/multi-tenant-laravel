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
        Schema::table('ec_products', function (Blueprint $table) {
            if (!Schema::hasColumn('ec_products', 'block_reason')) {
                $table->string('block_reason')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('block_reason');
        });
    }
};
