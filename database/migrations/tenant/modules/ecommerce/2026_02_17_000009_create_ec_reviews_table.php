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
        Schema::create('ec_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ec_products')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('ec_customers')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('ec_orders')->onDelete('set null');
            $table->integer('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_verified')->default(false); // Verified purchase
            $table->boolean('is_approved')->default(false); // Admin approval
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            $table->index('product_id');
            $table->index('customer_id');
            $table->index('is_approved');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_reviews');
    }
};
