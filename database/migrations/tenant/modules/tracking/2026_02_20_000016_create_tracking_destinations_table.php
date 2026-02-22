<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ec_tracking_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('ec_tracking_containers')->onDelete('cascade');
            $table->string('type'); // facebook_capi, ga4, tiktok, webhook
            $table->string('name');
            $table->json('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_tracking_destinations');
    }
};
