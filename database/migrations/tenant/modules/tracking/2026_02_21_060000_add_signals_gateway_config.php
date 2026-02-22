<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant_dynamic')->table('ec_tracking_containers', function (Blueprint $table) {
            $table->json('pipeline_config')->nullable()->after('extra_domains');
            $table->json('data_filters')->nullable()->after('pipeline_config');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant_dynamic')->table('ec_tracking_containers', function (Blueprint $table) {
            $table->dropColumn(['pipeline_config', 'data_filters']);
        });
    }
};
