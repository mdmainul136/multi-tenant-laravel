<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // 1. Tracking Containers (sGTM Config)
        Schema::connection($this->connection)->create('ec_tracking_containers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('container_id')->unique(); // GTM-XXXXXXX
            $table->string('domain')->nullable();     // ss.tenant-shop.com
            $table->string('preview_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();     // Power-up configs
            $table->timestamps();
        });

        // 2. Tracking Event Logs (Usage & Logic)
        Schema::connection($this->connection)->create('ec_tracking_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('ec_tracking_containers')->onDelete('cascade');
            $table->string('event_type');  // page_view, purchase, etc.
            $table->string('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->integer('status_code')->default(200);
            $table->timestamps();

            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_tracking_event_logs');
        Schema::connection($this->connection)->dropIfExists('ec_tracking_containers');
    }
};
