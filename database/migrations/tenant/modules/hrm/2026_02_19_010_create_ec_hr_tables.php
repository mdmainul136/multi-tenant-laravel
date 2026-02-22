<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant_dynamic';

    public function up(): void
    {
        // Departments first (referenced by staff)
        Schema::connection($this->connection)->create('ec_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Staff table
        Schema::connection($this->connection)->create('ec_staff', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('ec_departments')->onDelete('set null');
            $table->string('designation')->nullable();
            $table->string('role')->default('staff')->comment('admin/manager/staff/cashier');
            $table->decimal('salary', 15, 2)->default(0);
            $table->string('salary_type')->default('monthly')->comment('monthly/hourly/daily');
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active')->comment('active/inactive/terminated');
            $table->string('avatar')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('department_id');
        });

        // Attendance
        Schema::connection($this->connection)->create('ec_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('ec_staff')->onDelete('cascade');
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->string('status')->default('present')->comment('present/absent/late/half_day/leave/holiday');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
            $table->index('date');
            $table->index('staff_id');
        });

        // Leave requests
        Schema::connection($this->connection)->create('ec_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('ec_staff')->onDelete('cascade');
            $table->string('type')->comment('annual/sick/unpaid/maternity/paternity/other');
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedInteger('days')->default(1);
            $table->text('reason');
            $table->string('status')->default('pending')->comment('pending/approved/rejected');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('staff_id');
            $table->index('status');
            $table->index('from_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ec_leave_requests');
        Schema::connection($this->connection)->dropIfExists('ec_attendance');
        Schema::connection($this->connection)->dropIfExists('ec_staff');
        Schema::connection($this->connection)->dropIfExists('ec_departments');
    }
};
