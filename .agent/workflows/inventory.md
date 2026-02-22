---
description: Rules and workflow for working on the Inventory module
---

# Inventory Module Workflow

> Use this workflow (`/inventory`) whenever you are working on the Inventory module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change, you MUST:
1. Read `app/Modules/Inventory/DOCUMENTATION.md`.
2. Read `app/Modules/Inventory/module_task.md`.

### Rule 2: Model Standards
- All Inventory models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Inventory/` namespace.
- Table names MUST use `inv_` prefix (e.g., `inv_warehouses`, `inv_stock_logs`).

### Rule 3: Folder Structure
```
app/Modules/Inventory/
├── Actions/          # AdjustStockAction, TransferStockAction, SyncGlobalStockAction
├── Controllers/      # InventoryController, PurchaseOrderController, WarehouseController
├── DTOs/
├── Services/         # StockService
├── routes/api.php
└── module.json
```

### Rule 4: Stock Management
- Stock quantity MUST NEVER go negative (validate before decrement).
- All stock changes MUST be logged in `StockLog` with:
  - `type`: `adjustment`, `sale`, `return`, `transfer_in`, `transfer_out`, `purchase`
  - `quantity_change`: positive for additions, negative for deductions
  - `reference`: source identifier (order_id, transfer_id, etc.)
- Use `AdjustStockAction` for manual adjustments.

### Rule 5: Multi-Warehouse
- Each product can exist in multiple warehouses (`WarehouseInventory`).
- `min_stock` triggers low-stock alerts.
- `max_stock` prevents over-ordering.
- One warehouse MUST be marked as `is_default`.

### Rule 6: Stock Transfers
- Transfers MUST follow this flow:
  ```
  pending → approved → in_transit → received
                                   → rejected
  ```
- Transfer MUST deduct from source warehouse and add to destination warehouse.
- Transfer items MUST validate source has sufficient stock.
- Use `TransferStockAction` for all transfers.

### Rule 7: Purchase Orders
- PO statuses: `draft`, `sent`, `partial`, `received`, `cancelled`.
- PO number MUST be auto-generated and unique per tenant.
- When PO items are received, stock MUST be added via `StockLog`.
- PO total = sum of (quantity × unit_cost) for all items.

### Rule 8: Supplier Management
- Supplier `payment_terms`: `net_30`, `net_60`, `net_90`, `cod`, `prepaid`.
- Each supplier can have multiple purchase orders.

### Rule 9: Cross-Module Integration
- Ecommerce: Product stock is synced via `SyncGlobalStockAction`.
- POS: POS sales MUST trigger stock deduction.
- Finance: Purchase orders MUST create Finance ledger entries on payment.

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Read Context
### Step 2: Update Task Log
### Step 3: Check Migrations
```bash
dir database\migrations\tenant\modules\inventory\
```
### Step 4: Implement (follow rules above)
### Step 5: Verify (`php -l`)
### Step 6: Update Documentation
