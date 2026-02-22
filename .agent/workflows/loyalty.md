---
description: Rules and workflow for working on the Loyalty module
---

# Loyalty Module Workflow

> Use this workflow whenever you are working on the Loyalty module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
1. Read `app/Modules/Loyalty/DOCUMENTATION.md`.
2. Read `app/Modules/Loyalty/module_task.md`.

### Rule 2: Model Standards
- All Loyalty models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Loyalty/` namespace.
- Table names MUST use `loyalty_` prefix.

### Rule 3: Folder Structure
```
app/Modules/Loyalty/
├── Controllers/      # LoyaltyController
├── Services/         # LoyaltyService
├── database/         # Seeders
├── routes/api.php
└── module.json
```

### Rule 4: Points System
- Points earned per currency unit = `LoyaltyProgram.points_per_unit`.
- Points can only be redeemed if balance >= `min_points_redeem`.
- Points balance MUST NEVER go negative.
- All point transactions MUST be logged with `type`: `earn`, `redeem`, `expire`, `adjust`.

### Rule 5: Tiers
- Tiers are defined by `min_points` threshold.
- Higher tiers get a `multiplier` on earned points.
- `benefits` is a JSON field describing tier perks.
- Customers automatically move between tiers based on lifetime points.

### Rule 6: Cross-Module Integration
- Ecommerce: Order completion MUST trigger point earning.
- POS: POS sales MUST trigger point earning.
- CRM: Points are reflected in CRM customer profile.

---

// turbo-all
### Step 1-6: Follow standard `/module-maintenance` workflow steps.
