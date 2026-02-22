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
            $table->json('component_blueprint')->nullable()->after('config');
            $table->json('capabilities')->nullable()->after('component_blueprint');
            $table->boolean('is_premium')->default(false)->after('price');
            $table->json('developer_metadata')->nullable()->after('is_premium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['component_blueprint', 'capabilities', 'is_premium', 'developer_metadata']);
        });
    }
};
