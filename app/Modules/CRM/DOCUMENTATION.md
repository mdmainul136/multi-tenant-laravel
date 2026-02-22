# 👥 CRM Module — Complete Reference

> **Module Key**: `crm` | Customer Relationship Management.
> Contacts, deals, coupons, loyalty programs, customer lifecycle tracking.

---

## 📂 Directory Structure

```
app/Modules/CRM/
├── module.json
├── Actions/
│   ├── StoreCustomerAction.php       # Customer creation
│   └── UpdateCustomerAction.php      # Customer update
├── Controllers/
│   ├── ContactController.php         # Contact CRUD
│   └── DealController.php            # Deal pipeline CRUD
├── DTOs/ (1 file)
├── Services/ (empty)
├── database/
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/CRM — 9 models)

| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `Contact` | `crm_contacts` | `first_name`, `last_name`, `email`, `phone`, `company`, `status`, `source`, `tags` | `deals()`, `activities()` |
| `Deal` | `crm_deals` | `contact_id`, `title`, `value`, `stage`, `probability`, `expected_close_date` | `contact()`, `activities()` |
| `Activity` | `crm_activities` | `contact_id`, `deal_id`, `type`, `description`, `scheduled_at`, `completed_at` | `contact()`, `deal()` |
| `Customer` | `crm_customers` | `name`, `email`, `phone`, `address`, `total_orders`, `total_spent` | `points()`, `loyaltyTransactions()` |
| `Coupon` | `crm_coupons` | `code`, `type`, `value`, `min_order_amount`, `max_uses`, `expires_at` | `uses()` |
| `CouponUse` | `crm_coupon_uses` | `coupon_id`, `customer_id`, `order_id`, `discount_amount` | `coupon()`, `customer()` |
| `CustomerPoints` | `crm_customer_points` | `customer_id`, `program_id`, `balance`, `lifetime_earned` | `customer()`, `program()` |
| `LoyaltyProgram` | `crm_loyalty_programs` | `name`, `points_per_unit`, `min_points_redeem`, `is_active` | `customerPoints()` |
| `LoyaltyTransaction` | `crm_loyalty_transactions` | `customer_id`, `points`, `type`, `reference_type` | `customer()` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/CRM/module_task.md)
