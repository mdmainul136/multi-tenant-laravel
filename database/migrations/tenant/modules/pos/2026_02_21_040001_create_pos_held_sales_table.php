<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->string('customer_name')->nullable();
            $table->json('cart_data'); // Stores items, totals, etc.
            $table->string('notes')->nullable();
            $table->string('hold_reference')->nullable(); // Friendly name for the hold
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_held_sales');
    }
};
