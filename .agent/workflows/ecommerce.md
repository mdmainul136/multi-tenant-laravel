---
description: Rules and workflow for working on the Ecommerce module
---

# Ecommerce Module Workflow

> Use this workflow (`/ecommerce`) whenever you are working on the Ecommerce module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change, you MUST:
1. Read `app/Modules/Ecommerce/DOCUMENTATION.md`.
2. Read `app/Modules/Ecommerce/module_task.md`.
3. Read `app/Modules/Ecommerce/module.json`.

### Rule 2: Model Standards
- All Ecommerce models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Ecommerce/` namespace.
- Table names MUST use `ec_` prefix (e.g., `ec_products`, `ec_orders`, `ec_customers`).
- Price/monetary fields MUST be cast to `'decimal:2'`.
- JSON fields (like `items`, `metadata`) MUST be cast to `'array'`.
- Boolean fields (`is_active`, `is_featured`) MUST be cast to `'boolean'`.

### Rule 3: Folder Structure
```
app/Modules/Ecommerce/
├── Actions/          # PlaceOrderAction, StoreProductAction, etc.
├── Controllers/      # OrderController, ProductController, etc.
├── DTOs/             # Data transfer objects
├── Services/         # RefundService, WalletService
├── Jobs/             # Background tasks (import, sync)
├── Policies/         # Authorization policies
├── routes/api.php    # All API routes
└── module.json       # Module config
```

### Rule 4: Order Lifecycle
- Order statuses MUST follow this flow:
  ```
  pending → processing → shipped → delivered → completed
                                              → cancelled
                                              → returned
  ```
- NEVER skip lifecycle steps.
- Use `UpdateOrderStatusAction` for all status transitions.
- Stock MUST be reserved on order placement and released on cancellation.

### Rule 5: Product Management
- Product `slug` MUST be auto-generated from `name` and be unique.
- Product `sku` MUST be unique per tenant.
- Price fields: `price` (retail), `sale_price` (discounted), `cost` (purchase cost).
- Product variants MUST be linked via `ProductVariant` model.
- Images MUST be managed via `ProductImage` model with `position` ordering.

### Rule 6: Coupon & Discount Rules
- Coupon `code` MUST be unique per tenant.
- Coupon types: `percentage`, `fixed_amount`, `free_shipping`.
- Always check `usage_limit`, `min_order`, and `expires_at` before applying.
- Log every coupon use in `CouponUse` model.

### Rule 7: Customer Wallet
- Wallet balance MUST never go negative.
- All wallet operations MUST go through `WalletService`.
- Every credit/debit MUST create a `WalletTransaction` record.
- Refunds to wallet MUST use `RefundService`.

### Rule 8: Returns & Refunds
- Return requests MUST be linked to an order via `ReturnRequest`.
- Each return item MUST reference the original `OrderItem`.
- Refund methods: `original_payment`, `wallet_credit`, `manual`.
- Refund amount MUST NOT exceed the original order total.

### Rule 9: Cross-Module Integration
- Inventory: Stock updates MUST flow through `Inventory` module's `AdjustStockAction`.
- Finance: Order payments MUST create corresponding `Finance` ledger entries.
- Notifications: Order status changes MUST trigger notification templates.
- POS: POS sales MUST sync to Ecommerce order system.

### Rule 10: Naming Convention
- Controllers: `[Entity]Controller.php` (e.g., `OrderController.php`)
- Actions: `[Verb][Entity]Action.php` (e.g., `PlaceOrderAction.php`)
- Services: `[Entity]Service.php` (e.g., `RefundService.php`)

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Read Context
```bash
type app\Modules\Ecommerce\DOCUMENTATION.md
type app\Modules\Ecommerce\module_task.md
```

### Step 2: Update Task Log
Add new task to `module_task.md` with `⏳` status.

### Step 3: Check Migrations
```bash
dir database\migrations\tenant\modules\ecommerce\
```

### Step 4: Implement
Follow all Module Rules above.

### Step 5: Verify
```bash
php -l <modified_file_path>
```

### Step 6: Update Documentation
Update `DOCUMENTATION.md` and mark tasks `✅` in `module_task.md`.
