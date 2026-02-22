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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('api_key');
            $table->string('favicon_url')->nullable()->after('logo_url');
            $table->string('primary_color', 20)->default('#3b82f6')->after('favicon_url');
            $table->string('secondary_color', 20)->nullable()->after('primary_color');
            $table->string('facebook_url')->nullable()->after('secondary_color');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('twitter_url')->nullable()->after('instagram_url');
            $table->string('linkedin_url')->nullable()->after('twitter_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'favicon_url',
                'primary_color',
                'secondary_color',
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'linkedin_url',
            ]);
        });
    }
};
