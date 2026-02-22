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
        Schema::table('ior_hs_lookup_logs', function (Blueprint $table) {
            $table->string('type')->default('selection')->after('id')->index(); // selection, inference
            $table->string('product_name')->nullable()->after('hs_code');
            $table->string('input_hash', 64)->nullable()->index()->after('product_name'); // For content-based caching
            $table->string('provider')->nullable()->after('source'); // provider name like zonos, gemini
            $table->json('raw_response')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ior_hs_lookup_logs', function (Blueprint $table) {
            $table->dropColumn(['type', 'product_name', 'input_hash', 'provider', 'raw_response']);
        });
    }
};
