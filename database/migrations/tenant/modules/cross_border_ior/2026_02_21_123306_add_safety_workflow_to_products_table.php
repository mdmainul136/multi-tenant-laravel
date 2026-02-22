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
            if (!Schema::hasColumn('ec_products', 'content_status')) {
                $table->string('content_status')->default('pending_rewrite')->after('is_active')->index();
                // pending_rewrite | ready_for_review | approved
            }
            if (!Schema::hasColumn('ec_products', 'is_warehouse_verified')) {
                $table->boolean('is_warehouse_verified')->default(false)->after('content_status');
            }
            if (!Schema::hasColumn('ec_products', 'source_metadata')) {
                $table->json('source_metadata')->nullable()->after('ior_attributes');
                // Highly sensitive: original features, verbatim descriptions, seller names
            }
        });

        Schema::create('ior_blocked_sources', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique(); // e.g. amazon.com, walmart.com
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn(['content_status', 'is_warehouse_verified', 'source_metadata']);
        });
        Schema::dropIfExists('ior_blocked_sources');
    }
};
