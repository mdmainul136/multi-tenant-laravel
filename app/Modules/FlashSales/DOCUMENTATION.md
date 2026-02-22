# ⚡ FlashSales Module — Complete Reference

> **Module Key**: `flash_sales` | Time-limited promotional sales events.

## 📂 Directory Structure

```
app/Modules/FlashSales/
├── module.json
├── Controllers/
│   └── FlashSaleController.php         # Flash sale CRUD & scheduling
├── database/ (seeders)
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/FlashSales — 1 model)

| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `FlashSale` | `flash_sales` | `name`, `starts_at`, `ends_at`, `discount_type`, `discount_value`, `product_ids`, `is_active` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/FlashSales/module_task.md)
