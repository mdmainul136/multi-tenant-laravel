---
description: Rules and workflow for working on the Finance module
---

# Finance Module Workflow

> Use this workflow (`/finance`) whenever you are working on the Finance module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change, you MUST:
1. Read `app/Modules/Finance/DOCUMENTATION.md`.
2. Read `app/Modules/Finance/module_task.md`.

### Rule 2: Model Standards
- All Finance models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Finance/` namespace.
- Table names MUST use `fin_` prefix (e.g., `fin_accounts`, `fin_ledger`).

### Rule 3: Folder Structure
```
app/Modules/Finance/
├── Actions/          # CreateAccountAction, RecordDoubleEntryAction, etc.
├── Controllers/      # AccountController, TransactionController, ReportController
├── DTOs/
├── Services/         # LedgerService
├── routes/api.php
└── module.json
```

### Rule 4: Double-Entry Accounting (CRITICAL)
- **Every transaction MUST have balanced debits and credits** (Total Debits = Total Credits).
- Use `RecordDoubleEntryAction` for ALL transaction recording.
- NEVER directly insert into `fin_ledger` — always go through the Action.
- Each ledger entry MUST update the `running_balance` for its account.

### Rule 5: Chart of Accounts
- Account types: `asset`, `liability`, `equity`, `revenue`, `expense`.
- Account `code` MUST be unique per tenant.
- Accounts can be hierarchical (`parent_id` for sub-accounts).
- `opening_balance` is set during account creation only.

### Rule 6: Transaction Rules
- Transaction `reference` MUST be unique per tenant.
- Transaction `date` defaults to today but can be backdated.
- Every transaction MUST have a `description`.
- Once a ledger entry is created, it MUST NOT be modified (append-only).
- To correct errors, create a reversal transaction.

### Rule 7: Financial Reports
- Use `GetIncomeStatementAction` for P&L reports.
- Reports are date-range based (`from_date`, `to_date`).
- Revenue = sum of credits to revenue accounts.
- Expenses = sum of debits to expense accounts.
- Net Income = Revenue - Expenses.

### Rule 8: Currency
- `fin_currencies` stores exchange rates.
- One currency MUST be marked as `is_default`.
- All ledger entries are recorded in the default currency.

### Rule 9: Cross-Module Integration
- HRM: Payroll payments MUST create ledger entries via `RecordSalaryExpenseAction`.
- Ecommerce: Sales revenue MUST create ledger entries (debit: Cash/Receivable, credit: Revenue).
- Inventory: Purchase orders MUST create ledger entries (debit: Inventory, credit: Cash/Payable).

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Read Context
### Step 2: Update Task Log
### Step 3: Check Migrations
```bash
dir database\migrations\tenant\modules\finance\
```
### Step 4: Implement (follow rules above — DOUBLE-ENTRY IS NON-NEGOTIABLE)
### Step 5: Verify (`php -l`)
### Step 6: Update Documentation
