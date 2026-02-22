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
        if (!Schema::hasTable('ior_order_milestones')) {
            Schema::create('ior_order_milestones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('status'); // manifested, warehouse_received, dispatched, customs_clearance, delivered
                $table->string('location')->nullable(); // e.g. USA-NY, BD-DAC
                $table->string('message_en');
                $table->string('message_bn')->nullable();
                $table->json('metadata')->nullable();
                
                $table->timestamps();
                
                // Foreign key to ior_foreign_orders
                if (Schema::hasTable('ior_foreign_orders')) {
                    $table->foreign('order_id')->references('id')->on('ior_foreign_orders')->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ior_order_milestones');
    }
};
