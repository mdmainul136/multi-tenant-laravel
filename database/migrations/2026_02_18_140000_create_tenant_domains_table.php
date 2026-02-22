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
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_token')->nullable();
            $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
            $table->timestamps();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index('status');
        });

        // Also add domain to tenants table if not already there, 
        // as a redundant primary domain store for performance
        if (!Schema::hasColumn('tenants', 'domain')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('domain')->nullable()->after('tenant_id')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        
        if (Schema::hasColumn('tenants', 'domain')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }
};
