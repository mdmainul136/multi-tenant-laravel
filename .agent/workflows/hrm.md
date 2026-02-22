---
description: Rules and workflow for working on the HRM module
---

# HRM Module Workflow

> Use this workflow whenever you are working on the HRM module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
1. Read `app/Modules/HRM/DOCUMENTATION.md`.
2. Read `app/Modules/HRM/module_task.md`.

### Rule 2: Model Standards
- All HRM models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/HRM/` namespace.
- Table names MUST use `hr_` prefix (e.g., `hr_staff`, `hr_payrolls`).

### Rule 3: Folder Structure
```
app/Modules/HRM/
├── Actions/          # GenerateMonthlyPayrollAction, MarkAttendanceAction, etc.
├── Controllers/      # HrmController, PayrollController
├── DTOs/
├── Services/         # PayrollService
├── routes/api.php
└── module.json
```

### Rule 4: Staff Management
- Staff MUST be linked to `User` via `user_id`.
- `employee_id` MUST be unique per tenant.
- Staff `status`: `active`, `on_leave`, `terminated`, `suspended`.
- Staff belongs to a `Department` via `department_id`.

### Rule 5: Attendance
- One attendance record per staff per date.
- `clock_in` and `clock_out` are timestamps.
- `hours_worked` = difference between clock_out and clock_in.
- Attendance `status`: `present`, `absent`, `late`, `half_day`, `on_leave`.
- Use `MarkAttendanceAction` for all attendance operations.

### Rule 6: Leave Management
- Leave types: `annual`, `sick`, `casual`, `maternity`, `unpaid`.
- Leave requests MUST go through approval flow:
  ```
  pending → approved → (taken)
          → rejected
  ```
- `days` is auto-calculated from `start_date` to `end_date` (excluding weekends).
- `approved_by` MUST be set when status changes to `approved`.

### Rule 7: Payroll (CRITICAL)
- Use `GenerateMonthlyPayrollAction` to generate payroll for ALL active staff.
- Payroll formula:
  ```
  Net Salary = Basic Salary + Allowances - Deductions
  ```
- Payroll `status`: `draft`, `approved`, `paid`.
- Use `MarkPayrollAsPaidAction` to mark as paid.
- When marked as paid, MUST create Finance ledger entry via `RecordSalaryExpenseAction`.
- PayrollItems track individual components (housing, transport, tax, etc.).

### Rule 8: Settings
- HRM settings use key-value pattern via `HrSetting` model.
- Common keys: `working_hours`, `weekend_days`, `overtime_rate`, `annual_leave_days`.

### Rule 9: Cross-Module Integration
- Finance: Payroll payments MUST create double-entry ledger transactions.
- Notifications: Leave approvals/rejections MUST trigger notifications.

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1-6: Follow standard `/module-maintenance` workflow steps.
