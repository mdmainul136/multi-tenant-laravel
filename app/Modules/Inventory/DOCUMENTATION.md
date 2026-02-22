# 📦 Inventory Module — Complete Reference

> **Module Key**: `inventory` | Warehouse, stock, and supply chain management.
> Stock tracking, multi-warehouse transfers, purchase orders, and supplier management.

---

## 📂 Directory Structure

```
app/Modules/Inventory/
├── module.json
├── Actions/
│   ├── AdjustStockAction.php         # Manual stock adjustments (+/-)
│   ├── TransferStockAction.php       # Inter-warehouse transfers
│   └── SyncGlobalStockAction.php     # Global stock sync across warehouses
├── Controllers/
│   ├── InventoryController.php       # Stock CRUD & listing
│   ├── PurchaseOrderController.php   # PO management
│   └── WarehouseController.php       # Warehouse CRUD
├── DTOs/ (1 file)
├── Services/
│   └── StockService.php              # Stock calculation service
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Inventory — 8 models)

| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `Warehouse` | `inv_warehouses` | `name`, `code`, `address`, `is_default`, `is_active` | `inventory()` |
| `WarehouseInventory` | `inv_warehouse_inventory` | `warehouse_id`, `product_id`, `quantity`, `min_stock`, `max_stock` | `warehouse()`, `product()` |
| `StockLog` | `inv_stock_logs` | `warehouse_id`, `product_id`, `type`, `quantity_change`, `reference` | `warehouse()`, `product()` |
| `StockTransfer` | `inv_stock_transfers` | `from_warehouse_id`, `to_warehouse_id`, `status`, `approved_by` | `fromWarehouse()`, `toWarehouse()`, `items()` |
| `StockTransferItem` | `inv_stock_transfer_items` | `transfer_id`, `product_id`, `quantity` | `transfer()`, `product()` |
| `Supplier` | `inv_suppliers` | `name`, `email`, `phone`, `company`, `payment_terms` | `purchaseOrders()` |
| `PurchaseOrder` | `inv_purchase_orders` | `po_number`, `supplier_id`, `status`, `total`, `expected_date` | `supplier()`, `items()` |
| `PurchaseOrderItem` | `inv_purchase_order_items` | `purchase_order_id`, `product_id`, `quantity`, `unit_cost` | `purchaseOrder()`, `product()` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Inventory/module_task.md)
