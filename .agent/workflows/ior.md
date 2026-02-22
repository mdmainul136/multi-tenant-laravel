---
description: Rules and workflow for working on the CrossBorderIOR module
---

# CrossBorderIOR Module Workflow

> Use this workflow (`/ior`) whenever you are assigned to work on any part of the IOR module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
Before making ANY change to IOR code, you MUST:
1. Read `app/Modules/CrossBorderIOR/DOCUMENTATION.md` to understand the full architecture.
2. Read `app/Modules/CrossBorderIOR/module_task.md` to check current status and pending items.
3. Read `app/Modules/CrossBorderIOR/module.json` for active features.

### Rule 2: Model Standards
- All IOR models MUST extend `TenantBaseModel`.
- All IOR models MUST be in `app/Models/CrossBorderIOR/` namespace.
- Table names MUST use `ior_` prefix (e.g., `ior_foreign_orders`, `ior_settings`).
- JSON fields (like `scraped_data`, `pricing_breakdown`, `credentials`) MUST be cast to `'array'`.
- Decimal fields MUST specify precision (e.g., `'decimal:2'`, `'decimal:4'`).

### Rule 3: Pricing Logic
- NEVER hardcode exchange rates. Always use `ExchangeRateService::getUsdToBdt()`.
- NEVER hardcode margin percentages. Always read from `IorSetting::get('default_profit_margin')`.
- The pricing formula is:
  ```
  Final = ceil(BaseBDT + Customs + Shipping + Warehouse + Margin)
  ```
- Advance payment tiers: >৳100K=100%, >৳50K=70%, >৳20K=60%, else=50%.

### Rule 4: Order Lifecycle
- Order statuses MUST follow this flow:
  ```
  pending → sourcing → ordered → shipped → customs → delivered
                                                    → cancelled
  ```
- Use the constants defined in `IorForeignOrder` (e.g., `STATUS_PENDING`, `STATUS_SHIPPED`).
- NEVER skip lifecycle steps (e.g., cannot go from `pending` directly to `delivered`).

### Rule 5: Payment Gateways
- Supported gateways: bKash, Nagad, Stripe, SSLCommerz.
- All payment callbacks are PUBLIC routes (no auth middleware).
- All payment initiations are AUTHENTICATED routes.
- Every payment event MUST be logged to `IorTransactionLog`.

### Rule 6: Scraping & Sourcing
- Always check budget via `ScraperBillingService::canScrape()` before any scraping operation.
- Respect rate limits configured in `ior_scraper_settings`.
- All scraped data MUST be stored in `scraped_data` JSON field on the order.

### Rule 7: Courier Integration
- Supported couriers: Pathao, Steadfast, RedX, FedEx, DHL.
- Courier webhooks are mapped to specific tenant via URL: `/webhooks/{tenantId}/pathao`.
- Courier credentials are stored in `IorCourierConfig` model (JSON `credentials` field).

### Rule 8: Service Naming Convention
- Services MUST be placed in `app/Modules/CrossBorderIOR/Services/`.
- Service names MUST end with `Service` (e.g., `CourierBookingService`).
- Calculators end with `Calculator` (e.g., `WarehouseMarginCalculator`).

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1: Understand the Task
Read the IOR documentation and task log:
```bash
cat app/Modules/CrossBorderIOR/DOCUMENTATION.md
cat app/Modules/CrossBorderIOR/module_task.md
```

### Step 2: Check Related Migrations
```bash
dir database\migrations\tenant\modules\ior\
```

### Step 3: Identify Affected Files
List all files that will be modified. Check if they exist and are non-empty:
```bash
php -l app/Models/CrossBorderIOR/<ModelName>.php
```

### Step 4: Implement Changes
Follow the Module Rules above. Key reminders:
- Extend `TenantBaseModel` for all models.
- Use `IorSetting::get()` for configuration values.
- Log all payment events to `IorTransactionLog`.
- Validate order status transitions.

### Step 5: Verify
Run syntax check on all modified files:
```bash
php -l <modified_file_path>
```

### Step 6: Update Documentation
After implementation, update BOTH:
1. `app/Modules/CrossBorderIOR/DOCUMENTATION.md` — Add new models/services/endpoints.
2. `app/Modules/CrossBorderIOR/module_task.md` — Mark task as ✅ Done.

### Step 7: Think & Report
Before finishing, think about:
- Are there any edge cases not covered?
- Does the change affect other modules?
- Are there any missing validation rules?
- Should any new tests be written?

Report findings in the task log.
