<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Tenant;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('api_key')->nullable()->unique()->after('status');
        });

        // Generate keys for existing tenants
        Tenant::all()->each(function ($tenant) {
            $tenant->api_key = 'sk_' . Str::random(32);
            $tenant->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
