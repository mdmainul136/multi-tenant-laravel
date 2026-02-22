---
description: Rules and workflow for working on the POS module
---

# POS Module Workflow

> Use this workflow (`/pos`) whenever you are working on the Point of Sale module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change, you MUST:
1. Read `app/Modules/POS/DOCUMENTATION.md`.
2. Read `app/Modules/POS/module_task.md`.
3. Read `app/Modules/POS/module.json`.

### Rule 2: Model Standards
- All POS models MUST extend `TenantBaseModel`.
- All POS models MUST be in `app/Models/POS/` namespace.
- Table names MUST use `pos_` prefix (e.g., `pos_sales`, `pos_sessions`).
- Price fields MUST be cast to `'decimal:2'`.
- JSON fields (like `items` in held sales) MUST be cast to `'array'`.

### Rule 3: Folder Structure
```
app/Modules/POS/
├── Actions/          # ProcessCheckoutAction, OpenSessionAction, etc.
├── Controllers/      # PosController
├── DTOs/             # Data transfer objects
├── routes/api.php    # All POS routes
└── module.json
```

### Rule 4: Session Management
- A register session MUST be opened via `OpenSessionAction` before any sale.
- Sessions track `opening_cash` and `closing_cash` for end-of-day reconciliation.
- Only ONE session per user can be active at a time.
- Session MUST be closed before opening a new one.

### Rule 5: Checkout Flow
- All sales MUST go through `ProcessCheckoutAction`.
- Checkout flow:
  ```
  Scan Products → Apply Discounts → Calculate Tax → Select Payment → Confirm Sale
  ```
- Stock MUST be decremented immediately on sale confirmation.
- Sale number MUST be auto-generated and unique per session.

### Rule 6: Hold & Recall
- Use `HoldSaleAction` to park a cart for later.
- Use `RecallSaleAction` to bring back a held cart.
- Held sales are stored as JSON snapshots in `PosHeldSale`.
- Held sales are tied to the current session.

### Rule 7: Payment Methods
- Supported: `cash`, `card`, `mobile`, `split`.
- Split payment: a sale can have multiple `PosPayment` records.
- Total of all payments MUST equal or exceed the sale total.
- Change amount = Total payments - Sale total.

### Rule 8: ZATCA Integration (Saudi Arabia)
- If ZATCA module is enabled, every sale MUST generate a ZATCA QR code.
- Use `GenerateZatcaQrAction` for QR generation.
- QR code is stored in `PosSale.zatca_qr` field.

### Rule 9: Offline Mode
- POS supports offline sales via `SyncOfflineSalesAction`.
- Offline sales are stored locally and synced when connection is restored.
- Each offline sale MUST have a temporary UUID that maps to the final sale_number.

### Rule 10: Staff Authentication
- POS staff authenticate via PIN (not password).
- Use `VerifyStaffPinAction` for staff PIN validation.
- PIN is separate from the main user password.

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Read Context
### Step 2: Update Task Log
### Step 3: Check Migrations
```bash
dir database\migrations\tenant\modules\pos\
```
### Step 4: Implement (follow rules above)
### Step 5: Verify (`php -l`)
### Step 6: Update Documentation
