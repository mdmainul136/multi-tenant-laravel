---
description: Rules and workflow for working on the CRM module
---

# CRM Module Workflow

> Use this workflow (`/crm`) whenever you are working on the CRM module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change, you MUST:
1. Read `app/Modules/CRM/DOCUMENTATION.md`.
2. Read `app/Modules/CRM/module_task.md`.

### Rule 2: Model Standards
- All CRM models MUST extend `TenantBaseModel`.
- All CRM models MUST be in `app/Models/CRM/` namespace.
- Table names MUST use `crm_` prefix (e.g., `crm_contacts`, `crm_deals`).

### Rule 3: Folder Structure
```
app/Modules/CRM/
├── Actions/          # StoreCustomerAction, UpdateCustomerAction
├── Controllers/      # ContactController, DealController
├── DTOs/
├── Services/
├── routes/api.php
└── module.json
```

### Rule 4: Contact Management
- Every contact MUST have at least `first_name` and `email`.
- Contact `status`: `lead`, `prospect`, `customer`, `churned`.
- Tags are stored as JSON array.
- Contact source (`source` field): `website`, `referral`, `social`, `manual`, `import`.

### Rule 5: Deal Pipeline
- Deal stages MUST follow this flow:
  ```
  lead → qualified → proposal → negotiation → won
                                              → lost
  ```
- Deal `value` MUST be cast to `'decimal:2'`.
- `probability` reflects conversion likelihood (0-100%).
- `expected_close_date` MUST be set when stage >= `proposal`.

### Rule 6: Activity Tracking
- Activity types: `call`, `email`, `meeting`, `note`, `task`.
- Activities can be linked to both Contact AND Deal simultaneously.
- Scheduled activities (`scheduled_at`) track upcoming tasks.
- Completed activities MUST set `completed_at` timestamp.

### Rule 7: Coupon System
- Coupon `code` MUST be unique per tenant.
- Coupon types: `percentage`, `fixed_amount`, `free_shipping`, `buy_x_get_y`.
- Validate: `min_order_amount`, `max_uses`, `expires_at`, `max_discount_amount`.
- Every coupon redemption MUST create a `CouponUse` record.

### Rule 8: Loyalty & Points
- Points are earned via `LoyaltyProgram` configuration.
- Points per unit currency = `points_per_unit` on the program.
- Minimum redemption threshold = `min_points_redeem`.
- `CustomerPoints` tracks per-program balance.
- `LoyaltyTransaction` logs every earn/redeem event.

### Rule 9: Cross-Module Integration
- Ecommerce orders MUST update CRM customer's `total_orders` and `total_spent`.
- Coupon usage from Ecommerce MUST be logged in CRM `CouponUse`.
- Loyalty points from POS sales MUST flow through CRM loyalty system.

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Read Context
### Step 2: Update Task Log
### Step 3: Check Migrations
```bash
dir database\migrations\tenant\modules\crm\
```
### Step 4: Implement (follow rules above)
### Step 5: Verify (`php -l`)
### Step 6: Update Documentation
