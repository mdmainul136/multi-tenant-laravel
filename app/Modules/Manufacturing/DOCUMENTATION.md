# 🏭 Manufacturing Module — Complete Reference

> **Module Key**: `manufacturing` | Bill of Materials (BOM) and production order management.

## 📂 Directory Structure

```
app/Modules/Manufacturing/
├── module.json
├── Actions/
│   ├── CreateBomAction.php               # Bill of materials creation
│   ├── StartProductionAction.php         # Begin manufacturing order
│   └── CompleteProductionAction.php       # Complete & stock update
├── Controllers/
│   └── ManufacturingController.php       # All manufacturing endpoints
├── DTOs/ (1 file)
├── Services/
│   └── ProductionService.php             # Production calculations
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Manufacturing — 3 models)

| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `Bom` | `mfg_boms` | `product_id`, `name`, `version`, `is_active` |
| `BomItem` | `mfg_bom_items` | `bom_id`, `material_id`, `quantity`, `unit`, `waste_percentage` |
| `ManufacturingOrder` | `mfg_orders` | `bom_id`, `quantity`, `status`, `started_at`, `completed_at` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Manufacturing/module_task.md)
