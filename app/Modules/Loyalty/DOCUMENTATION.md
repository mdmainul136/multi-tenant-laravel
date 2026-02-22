# 🎁 Loyalty Module — Complete Reference

> **Module Key**: `loyalty` | Customer loyalty programs, points, and tiers.

## 📂 Directory Structure

```
app/Modules/Loyalty/
├── module.json
├── Controllers/
│   └── LoyaltyController.php           # Program & points management
├── Services/
│   └── LoyaltyService.php              # Points calculation
├── database/ (seeders/migrations)
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Loyalty — 3 models)

| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `LoyaltyProgram` | `loyalty_programs` | `name`, `points_per_unit`, `min_points_redeem`, `is_active` |
| `LoyaltyPoint` | `loyalty_points` | `customer_id`, `program_id`, `balance`, `lifetime_earned` |
| `LoyaltyTier` | `loyalty_tiers` | `program_id`, `name`, `min_points`, `multiplier`, `benefits` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Loyalty/module_task.md)
