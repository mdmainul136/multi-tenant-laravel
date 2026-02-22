<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // 1. Payrolls Table
        Schema::connection($this->connection)->create('ec_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('ec_staff')->onDelete('cascade');
            $table->string('month'); // e.g., 2026-02
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_allowance', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2);
            $table->string('status')->default('draft')->comment('draft/generated/paid');
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'month']);
            $table->index('month');
        });

        // 2. Payroll Items (Allowances & Deductions)
        Schema::connection($this->connection)->create('ec_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('ec_payrolls')->onDelete('cascade');
            $table->string('title'); // e.g., Conveyance, Bonus, Tax, Advance Deduction
            $table->enum('type', ['allowance', 'deduction']);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        // 3. Settings for HR (Working hours, late threshold, etc.)
        Schema::connection($this->connection)->create('ec_hr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_payroll_items');
        Schema::connection($this->connection)->dropIfExists('ec_payrolls');
        Schema::connection($this->connection)->dropIfExists('ec_hr_settings');
    }
};
