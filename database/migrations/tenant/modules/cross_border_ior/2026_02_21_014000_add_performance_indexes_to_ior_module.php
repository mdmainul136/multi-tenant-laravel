<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds performance indexes for IOR order searches (tracking, email, phone)
     * which are critical for the frictionless guest tracking experience.
     */
    public function up(): void
    {
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            // Index tracking number for status polling and search
            if (!Schema::hasIndex('ior_foreign_orders', 'ior_foreign_orders_tracking_number_index')) {
                $table->index('tracking_number');
            }

            // Index guest identification for tracking verification
            if (!Schema::hasIndex('ior_foreign_orders', 'ior_foreign_orders_guest_email_index')) {
                $table->index('guest_email');
            }
            if (!Schema::hasIndex('ior_foreign_orders', 'ior_foreign_orders_guest_phone_index')) {
                $table->index('guest_phone');
            }

            // Index shipping phone as it is used as a fallback for guest_phone
            if (!Schema::hasIndex('ior_foreign_orders', 'ior_foreign_orders_shipping_phone_index')) {
                $table->index('shipping_phone');
            }

            // Index source marketplace for admin filtering
            if (!Schema::hasIndex('ior_foreign_orders', 'ior_foreign_orders_source_marketplace_index')) {
                $table->index('source_marketplace');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ior_foreign_orders', function (Blueprint $table) {
            $table->dropIndex(['tracking_number']);
            $table->dropIndex(['guest_email']);
            $table->dropIndex(['guest_phone']);
            $table->dropIndex(['shipping_phone']);
            $table->dropIndex(['source_marketplace']);
        });
    }
    
    /**
     * Helper to check for existing index (standard Laravel naming).
     */
    private function hasIndex(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $schemaManager = $conn->getDoctrineSchemaManager();
        $indexes = $schemaManager->listTableIndexes($table);
        return array_key_exists($index, $indexes);
    }
};
