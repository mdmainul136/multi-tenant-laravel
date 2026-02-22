<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->table('ec_tracking_destinations', function (Blueprint $table) {
            $table->json('mappings')->after('credentials')->nullable(); // source_field => target_field
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('ec_tracking_destinations', function (Blueprint $table) {
            $table->dropColumn('mappings');
        });
    }
};
