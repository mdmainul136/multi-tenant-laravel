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
        Schema::connection('mysql')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            
            $table->string('invoice_number')->unique(); // INV-202602-00001
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            
            $table->string('subscription_type'); // monthly, annual, lifetime
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            $table->enum('status', ['draft', 'paid', 'pending', 'cancelled', 'refunded'])->default('pending');
            
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            
            $table->timestamps();
            
            $table->index('invoice_number');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('invoices');
    }
};
