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
        Schema::table('ior_scraper_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ior_scraper_settings', 'use_proxy')) {
                $table->boolean('use_proxy')->default(false)->after('is_active');
                $table->string('proxy_type')->default('shared')->after('use_proxy'); // shared, dedicated, rotating
                $table->string('proxy_host')->nullable()->after('proxy_type');
                $table->string('proxy_port')->nullable()->after('proxy_host');
                $table->string('proxy_user')->nullable()->after('proxy_port');
                $table->string('proxy_password')->nullable()->after('proxy_user');
                $table->timestamp('proxy_expires_at')->nullable()->after('proxy_password');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ior_scraper_settings', function (Blueprint $table) {
            $table->dropColumn([
                'use_proxy',
                'proxy_type',
                'proxy_host',
                'proxy_port',
                'proxy_user',
                'proxy_password',
                'proxy_expires_at'
            ]);
        });
    }
};
