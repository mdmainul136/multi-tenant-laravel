<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HRM\Controllers\StaffController;
use App\Modules\HRM\Controllers\PayrollController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class,
    'module.access:hrm',
    'quota.enforce'
])->prefix('hrm')->group(function () {
        
        // ── Staff & HR Management ──────────────────────────────────────────
        Route::controller(StaffController::class)->group(function () {
            // KPI
            Route::get('/stats', 'stats');

            // Departments
            Route::get('/departments',        'getDepartments');
            Route::post('/departments',       'storeDepartment');
            Route::put('/departments/{id}',   'updateDepartment');
            Route::delete('/departments/{id}','destroyDepartment');

            // Staff
            Route::get('/staff',        'index');
            Route::post('/staff',       'store');
            Route::get('/staff/{id}',    'show');
            Route::put('/staff/{id}',    'update');
            Route::delete('/staff/{id}', 'destroy');

            // Attendance
            Route::post('/attendance',  'markAttendance');
            Route::get('/attendance',   'getAttendance');
            Route::post('/check-in',    'checkIn');
            Route::post('/check-out',   'checkOut');

            // Leaves
            Route::get('/leaves',            'getLeaves');
            Route::post('/leaves',           'storeLeave');
            Route::put('/leaves/{id}/status','updateLeaveStatus');
        });

        // ── Payroll Management ─────────────────────────────────────────────
        Route::prefix('payroll')->controller(PayrollController::class)->group(function () {
            Route::get('/',            'index');
            Route::post('/generate',   'generate');
            Route::get('/{id}',        'show');
            Route::post('/{id}/pay',   'pay');
        });

    });
