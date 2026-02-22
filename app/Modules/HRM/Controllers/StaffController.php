<?php

namespace App\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HRM\{Department, Staff, Attendance, LeaveRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // DASHBOARD STATS
    // ══════════════════════════════════════════════════════════════

    public function stats()
    {
        $now          = now();
        $totalStaff   = Staff::count();
        $activeStaff  = Staff::active()->count();
        $today        = today()->toDateString();

        $presentToday   = Attendance::whereDate('date', $today)->where('status', 'present')->count();
        $absentToday    = Attendance::whereDate('date', $today)->where('status', 'absent')->count();
        $pendingLeaves  = LeaveRequest::where('status', 'pending')->count();

        $monthlyPayroll = Staff::active()
            ->where('salary_type', 'monthly')
            ->sum('salary');

        $byDepartment = Department::withCount(['staff as active_count' => fn($q) => $q->where('status', 'active')])
                                  ->orderByDesc('active_count')
                                  ->get(['id', 'name', 'active_count']);

        $byRole = Staff::active()
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_staff'     => $totalStaff,
                'active_staff'    => $activeStaff,
                'present_today'   => $presentToday,
                'absent_today'    => $absentToday,
                'pending_leaves'  => $pendingLeaves,
                'monthly_payroll' => (float) $monthlyPayroll,
                'by_department'   => $byDepartment,
                'by_role'         => $byRole,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DEPARTMENTS
    // ══════════════════════════════════════════════════════════════

    public function getDepartments()
    {
        return response()->json([
            'success' => true,
            'data'    => Department::withCount('staff')->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255|unique:tenant_dynamic.ec_departments,name',
            'description'  => 'nullable|string',
            'manager_name' => 'nullable|string|max:255',
            'is_active'    => 'nullable|boolean',
        ]);

        return response()->json([
            'success' => true,
            'data'    => Department::create($validated),
        ], 201);
    }

    public function updateDepartment(Request $request, $id)
    {
        $dept = Department::findOrFail($id);
        $dept->update($request->validate([
            'name'         => "sometimes|required|string|max:255|unique:tenant_dynamic.ec_departments,name,{$id}",
            'description'  => 'nullable|string',
            'manager_name' => 'nullable|string|max:255',
            'is_active'    => 'nullable|boolean',
        ]));
        return response()->json(['success' => true, 'data' => $dept->fresh()]);
    }

    public function destroyDepartment($id)
    {
        $dept = Department::findOrFail($id);
        if ($dept->staff()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete department with staff assigned'], 422);
        }
        $dept->delete();
        return response()->json(['success' => true, 'message' => 'Department deleted']);
    }

    // ══════════════════════════════════════════════════════════════
    // STAFF CRUD
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $query = Staff::with('department');

        if ($request->filled('search'))        $query->search($request->search);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('role'))          $query->where('role', $request->role);

        $perPage = min((int)$request->get('per_page', 15), 100);
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->paginate($perPage)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'nullable|email|max:255|unique:tenant_dynamic.ec_staff,email',
            'phone'             => 'nullable|string|max:50',
            'department_id'     => 'nullable|exists:tenant_dynamic.ec_departments,id',
            'designation'       => 'nullable|string|max:255',
            'role'              => 'nullable|in:admin,manager,staff,cashier,warehouse',
            'salary'            => 'nullable|numeric|min:0',
            'salary_type'       => 'nullable|in:monthly,hourly,daily',
            'hire_date'         => 'nullable|date',
            'status'            => 'nullable|in:active,inactive,terminated',
            'address'           => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);

        $dto = \App\Modules\HRM\DTOs\StaffDTO::fromRequest($validated);
        $staff = app(\App\Modules\HRM\Actions\StoreStaffAction::class)->execute($dto);

        return response()->json([
            'success' => true,
            'message' => 'Staff member created',
            'data'    => $staff->load('department'),
        ], 201);
    }

    public function show($id)
    {
        $staff = Staff::with(['department', 'leaveRequests' => fn($q) => $q->latest()->limit(5)])
                      ->findOrFail($id);

        $attendanceSummary = Attendance::where('staff_id', $id)
            ->whereMonth('date', now()->month)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'staff'              => $staff,
                'attendance_summary' => $attendanceSummary,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'email'             => "nullable|email|max:255|unique:tenant_dynamic.ec_staff,email,{$id}",
            'phone'             => 'nullable|string|max:50',
            'department_id'     => 'nullable|exists:tenant_dynamic.ec_departments,id',
            'designation'       => 'nullable|string|max:255',
            'role'              => 'nullable|in:admin,manager,staff,cashier,warehouse',
            'salary'            => 'nullable|numeric|min:0',
            'salary_type'       => 'nullable|in:monthly,hourly,daily',
            'hire_date'         => 'nullable|date',
            'end_date'          => 'nullable|date',
            'status'            => 'nullable|in:active,inactive,terminated',
            'address'           => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);
        
        $dto = \App\Modules\HRM\DTOs\StaffDTO::fromRequest($validated);
        $staff = app(\App\Modules\HRM\Actions\UpdateStaffAction::class)->execute((int)$id, $dto);

        return response()->json(['success' => true, 'data' => $staff->load('department')]);
    }

    public function destroy($id)
    {
        Staff::findOrFail($id)->update(['status' => 'terminated']);
        return response()->json(['success' => true, 'message' => 'Staff member deactivated']);
    }

    // ══════════════════════════════════════════════════════════════
    // ATTENDANCE
    // ══════════════════════════════════════════════════════════════

    public function markAttendance(Request $request)
    {
        $validated = $request->validate([
            'records'              => 'required|array|min:1',
            'records.*.staff_id'   => 'required|exists:tenant_dynamic.ec_staff,id',
            'records.*.date'       => 'required|date',
            'records.*.status'     => 'required|in:present,absent,late,half_day,leave,holiday',
            'records.*.check_in'   => 'nullable|date_format:H:i',
            'records.*.check_out'  => 'nullable|date_format:H:i',
            'records.*.note'       => 'nullable|string',
        ]);

        $results = app(\App\Modules\HRM\Actions\MarkAttendanceAction::class)->execute($validated['records']);

        return response()->json(['success' => true, 'data' => $results]);
    }

    /** Individual employee Check-In */
    public function checkIn(Request $request)
    {
        $request->validate(['staff_id' => 'required|exists:tenant_dynamic.ec_staff,id']);
        
        $attendance = Attendance::updateOrCreate(
            ['staff_id' => $request->staff_id, 'date' => today()],
            [
                'status' => 'present',
                'check_in' => now()->format('H:i'),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Checked in successfully', 'data' => $attendance]);
    }

    /** Individual employee Check-Out */
    public function checkOut(Request $request)
    {
        $request->validate(['staff_id' => 'required|exists:tenant_dynamic.ec_staff,id']);
        
        $attendance = Attendance::where('staff_id', $request->staff_id)
            ->where('date', today())
            ->firstOrFail();
            
        $attendance->update(['check_out' => now()->format('H:i')]);
        $attendance->update(['hours_worked' => $attendance->calculateHours()]);

        return response()->json(['success' => true, 'message' => 'Checked out successfully', 'data' => $attendance]);
    }

    public function getAttendance(Request $request)
    {
        $request->validate([
            'date'   => 'nullable|date',
            'month'  => 'nullable|integer|min:1|max:12',
            'year'   => 'nullable|integer|min:2020',
            'staff_id' => 'nullable|exists:tenant_dynamic.ec_staff,id',
        ]);

        $query = Attendance::with('staff');

        if ($request->filled('staff_id')) $query->where('staff_id', $request->staff_id);
        if ($request->filled('date'))     $query->whereDate('date', $request->date);
        elseif ($request->filled('month')) {
            $year  = $request->get('year', now()->year);
            $query->whereYear('date', $year)->whereMonth('date', $request->month);
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('date', 'desc')->paginate(50)]);
    }

    // ══════════════════════════════════════════════════════════════
    // LEAVE MANAGEMENT
    // ══════════════════════════════════════════════════════════════

    public function getLeaves(Request $request)
    {
        $query = LeaveRequest::with('staff');
        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('staff_id')) $query->where('staff_id', $request->staff_id);

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(20)]);
    }

    public function storeLeave(Request $request)
    {
        $validated = $request->validate([
            'staff_id'  => 'required|exists:tenant_dynamic.ec_staff,id',
            'type'      => 'required|in:annual,sick,unpaid,maternity,paternity,other',
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'reason'    => 'required|string|max:500',
        ]);

        $from = \Carbon\Carbon::parse($validated['from_date']);
        $to   = \Carbon\Carbon::parse($validated['to_date']);
        $validated['days'] = $from->diffInWeekdays($to) + 1;

        $leave = LeaveRequest::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted',
            'data'    => $leave->load('staff'),
        ], 201);
    }

    public function updateLeaveStatus(Request $request, $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $request->validate([
            'status'     => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if (!$leave->canTransitionTo($request->status)) {
            return response()->json(['success' => false, 'message' => 'Cannot update this leave'], 422);
        }

        $leave->update([
            'status'      => $request->status,
            'admin_note'  => $request->admin_note,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $leave->fresh('staff')]);
    }
}
