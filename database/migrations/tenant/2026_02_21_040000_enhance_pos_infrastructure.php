<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Branches Table
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Saudi Arabia');
            $table->string('vat_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 2. Warehouses Table (linked to branch)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Update Users for POS PIN and Branch
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_code', 4)->nullable()->after('password');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
        });

        // 4. Update POS Sessions for Branch/Warehouse
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        // 5. Update POS Sales for Branch/Warehouse and ZATCA compliance
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $table->text('zatca_qr')->nullable(); // Base64 encoded TLV for ZATCA
            $table->string('offline_id')->nullable()->unique(); // Unique ID from PWA indexedDB
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'warehouse_id', 'zatca_qr', 'offline_id']);
        });
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'warehouse_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_code', 'branch_id']);
        });
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
    }
};
