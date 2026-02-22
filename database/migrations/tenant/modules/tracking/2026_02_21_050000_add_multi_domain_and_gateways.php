<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant_dynamic')->table('ec_tracking_containers', function (Blueprint $table) {
            $table->json('extra_domains')->nullable()->after('domain'); // Multi-domain support
        });

        Schema::connection('tenant_dynamic')->table('ec_tracking_destinations', function (Blueprint $table) {
            $table->boolean('is_gateway')->default(false)->after('is_active'); // Dedicated gateway flag
            $table->integer('delay_minutes')->default(0)->after('is_gateway'); // Request delay (0-1500 min)
        });
    }

    public function down(): void
    {
        Schema::connection('tenant_dynamic')->table('ec_tracking_containers', function (Blueprint $table) {
            $table->dropColumn('extra_domains');
        });

        Schema::connection('tenant_dynamic')->table('ec_tracking_destinations', function (Blueprint $table) {
            $table->dropColumn(['is_gateway', 'delay_minutes']);
        });
    }
};
