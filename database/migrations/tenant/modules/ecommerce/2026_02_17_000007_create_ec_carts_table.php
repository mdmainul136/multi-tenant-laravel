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
        Schema::create('ec_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('ec_customers')->onDelete('cascade');
            $table->string('session_id')->nullable(); // For guest users
            $table->json('items'); // [{product_id, variant_id, quantity, price}]
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('session_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_carts');
    }
};
