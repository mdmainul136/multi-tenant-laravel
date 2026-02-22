<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add outbound logistics fields to ior_foreign_orders
        if (Schema::hasTable('ior_foreign_orders')) {
            Schema::table('ior_foreign_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('ior_foreign_orders', 'shipment_batch_id')) {
                    $table->unsignedBigInteger('shipment_batch_id')->nullable()->index()->after('courier_code');
                }
                if (!Schema::hasColumn('ior_foreign_orders', 'intl_tracking_number')) {
                    $table->string('intl_tracking_number')->nullable()->after('shipment_batch_id');
                }
                if (!Schema::hasColumn('ior_foreign_orders', 'intl_courier_code')) {
                    $table->string('intl_courier_code', 20)->nullable()->after('intl_tracking_number');
                }
                if (!Schema::hasColumn('ior_foreign_orders', 'dispatched_at')) {
                    $table->timestamp('dispatched_at')->nullable()->after('intl_courier_code');
                }
                if (!Schema::hasColumn('ior_foreign_orders', 'customs_cleared_at')) {
                    $table->timestamp('customs_cleared_at')->nullable()->after('dispatched_at');
                }
                if (!Schema::hasColumn('ior_foreign_orders', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('customs_cleared_at');
                }
            });
        }

        // Enhance shipment batches
        if (Schema::hasTable('ior_shipment_batches')) {
            Schema::table('ior_shipment_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('ior_shipment_batches', 'order_count')) {
                    $table->unsignedInteger('order_count')->default(0)->after('total_volumetric_weight');
                }
                if (!Schema::hasColumn('ior_shipment_batches', 'customs_cleared_at')) {
                    $table->timestamp('customs_cleared_at')->nullable()->after('estimated_arrival');
                }
                if (!Schema::hasColumn('ior_shipment_batches', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable()->after('customs_cleared_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_batch_id', 'intl_tracking_number', 'intl_courier_code',
                'dispatched_at', 'customs_cleared_at', 'delivered_at'
            ]);
        });
        Schema::table('ior_shipment_batches', function (Blueprint $table) {
            $table->dropColumn(['order_count', 'customs_cleared_at', 'arrived_at']);
        });
    }
};
