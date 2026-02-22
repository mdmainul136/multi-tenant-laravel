<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->table('ec_tracking_containers', function (Blueprint $table) {
            $table->string('api_secret')->after('container_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('ec_tracking_containers', function (Blueprint $table) {
            $table->dropColumn('api_secret');
        });
    }
};
