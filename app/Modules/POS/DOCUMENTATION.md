# 🏪 POS Module — Complete Reference

> [!IMPORTANT]
> **Module Key**: `pos` | Point of Sale for retail/in-store transactions.
> Manages register sessions, checkout, cart hold/recall, ZATCA QR, and payment tracking.

---

## 📂 Directory Structure

```
app/Modules/POS/
├── module.json
├── Actions/
│   ├── ProcessCheckoutAction.php     # Complete POS sale processing
│   ├── OpenSessionAction.php         # Open a new register session
│   ├── HoldSaleAction.php            # Park/hold a cart for later
│   ├── RecallSaleAction.php          # Recall a held cart
│   ├── SyncOfflineSalesAction.php    # Sync offline sales to server
│   ├── GenerateZatcaQrAction.php     # ZATCA Phase 2 QR code gen
│   └── VerifyStaffPinAction.php      # Staff PIN authentication
├── Controllers/
│   └── PosController.php             # All POS endpoints
├── DTOs/ (1 file)
└── routes/
    └── api.php
```

---

## 🗄️ Data Models (app/Models/POS — 6 models)

| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `PosSale` | `pos_sales` | `sale_number`, `session_id`, `customer_id`, `subtotal`, `tax`, `discount`, `total`, `payment_method`, `zatca_qr` | `session()`, `items()`, `customer()`, `payments()` |
| `PosSaleItem` | `pos_sale_items` | `sale_id`, `product_id`, `variant_id`, `quantity`, `unit_price`, `discount`, `total` | `sale()`, `product()` |
| `PosSession` | `pos_sessions` | `user_id`, `opened_at`, `closed_at`, `opening_cash`, `closing_cash`, `status` | `user()`, `sales()` |
| `PosPayment` | `pos_payments` | `sale_id`, `method`, `amount`, `reference`, `status` | `sale()` |
| `PosHeldSale` | `pos_held_sales` | `session_id`, `customer_name`, `items`, `note` | `session()` |
| `PosProduct` | `pos_products` | `product_id`, `barcode`, `quick_key_position`, `is_active` | `product()` |

---

## 🔗 Module Task Log

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/POS/module_task.md)
