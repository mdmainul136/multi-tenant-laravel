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
        if (!Schema::hasTable('ior_shipment_batches')) {
            Schema::create('ior_shipment_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_number')->unique()->index(); // e.g. BATCH-2026-0001
                $table->string('carrier')->nullable(); // FedEx, DHL, Aramex
                $table->string('master_tracking_no')->nullable()->index();
                $table->string('origin_warehouse')->default('USA-NY');
                $table->string('destination')->default('BD-DAC');
                $table->string('status')->default('pending'); // pending, manifested, in_transit, customs, received
                
                $table->decimal('total_weight_kg', 10, 2)->default(0);
                $table->decimal('total_volumetric_weight', 10, 2)->default(0);
                
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('estimated_arrival')->nullable();
                
                $table->timestamps();
            });
        }

        // Link orders to batches
        if (Schema::hasTable('ec_orders')) {
            Schema::table('ec_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('ec_orders', 'shipment_batch_id')) {
                    $table->unsignedBigInteger('shipment_batch_id')->nullable()->after('id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_orders', function (Blueprint $table) {
            $table->dropColumn('shipment_batch_id');
        });
        Schema::dropIfExists('ior_shipment_batches');
    }
};
