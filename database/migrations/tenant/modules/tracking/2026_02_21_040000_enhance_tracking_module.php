<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // 1. Power-Ups & Docker Control Plane columns on containers
        Schema::connection($this->connection)->table('ec_tracking_containers', function (Blueprint $table) {
            $table->json('power_ups')->nullable()->after('settings');         // Enabled power-ups list
            $table->string('docker_container_id')->nullable()->after('power_ups');
            $table->enum('docker_status', ['pending', 'running', 'stopped', 'error'])->default('pending')->after('docker_container_id');
            $table->integer('docker_port')->nullable()->after('docker_status');
            $table->timestamp('provisioned_at')->nullable()->after('docker_port');
        });

        // 2. Usage Metering Table (Daily rollup per container)
        Schema::connection($this->connection)->create('ec_tracking_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('ec_tracking_containers')->onDelete('cascade');
            $table->date('date');
            $table->unsignedBigInteger('events_received')->default(0);
            $table->unsignedBigInteger('events_forwarded')->default(0);
            $table->unsignedBigInteger('events_dropped')->default(0);
            $table->unsignedBigInteger('power_ups_invoked')->default(0);
            $table->timestamps();

            $table->unique(['container_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_tracking_usage');
        Schema::connection($this->connection)->table('ec_tracking_containers', function (Blueprint $table) {
            $table->dropColumn(['power_ups', 'docker_container_id', 'docker_status', 'docker_port', 'provisioned_at']);
        });
    }
};
