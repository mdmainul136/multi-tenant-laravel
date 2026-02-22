<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_database_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->decimal('database_size_mb', 12, 2)->default(0);
            $table->decimal('data_size_mb', 12, 2)->default(0);
            $table->decimal('index_size_mb', 12, 2)->default(0);
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->string('largest_table')->nullable();
            $table->decimal('largest_table_size_mb', 12, 2)->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_database_stats');
    }
};
