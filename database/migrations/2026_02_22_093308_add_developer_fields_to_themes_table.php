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
        Schema::table('themes', function (Blueprint $table) {
            $table->unsignedBigInteger('developer_id')->nullable()->after('id');
            $table->string('version')->default('1.0.0')->after('vertical');
            $table->string('submission_status')->default('draft')->after('version');
            $table->decimal('price', 10, 2)->default(0.00)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['developer_id', 'version', 'submission_status', 'price']);
        });
    }
};
