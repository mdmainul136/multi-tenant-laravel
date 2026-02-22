# 🛒 Ecommerce Module — Complete Reference

> [!IMPORTANT]
> **Module Key**: `ecommerce` | Core online store functionality.
> Handles products, orders, customers, payments, returns, refunds, and reporting.

---

## 📂 Directory Structure

```
app/Modules/Ecommerce/
├── module.json
├── Actions/
│   ├── PlaceOrderAction.php          # Order creation with pricing, stock, and coupon logic
│   ├── StoreProductAction.php        # Product creation validation
│   ├── UpdateProductAction.php       # Product update logic
│   └── UpdateOrderStatusAction.php   # Order status transition
├── Controllers/
│   ├── CategoryController.php        # Category CRUD
│   ├── EcommerceDashboardController  # Dashboard stats & KPIs
│   ├── OrderController.php           # Order CRUD & listing
│   ├── ProductController.php         # Product CRUD
│   ├── ProductImageController.php    # Image upload & management
│   ├── RefundController.php          # Refund processing
│   ├── ReportsController.php         # Revenue, sales, product reports
│   ├── ReturnController.php          # Return request management
│   ├── ReviewController.php          # Customer review moderation
│   ├── StoreController.php           # Storefront public API
│   ├── TaxCurrencyController.php     # Tax & currency config
│   ├── TenantDomainController.php    # Custom domain management
│   ├── TenantSettingsController.php  # Store settings
│   └── WalletController.php          # Customer wallet management
├── DTOs/ (2 files)
├── Services/
│   ├── RefundService.php             # Refund calculation & processing
│   └── WalletService.php             # Wallet credit/debit operations
└── routes/
    └── api.php
```

---

## 🗄️ Data Models (app/Models/Ecommerce — 25 models)

### Core Commerce
| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `Product` | `ec_products` | `name`, `slug`, `sku`, `price`, `sale_price`, `cost`, `stock_quantity`, `is_active` | `categoryData()` |
| `Order` | `ec_orders` | `order_number`, `customer_id`, `status`, `payment_status`, `total` | `customer()`, `items()` |
| `OrderItem` | `ec_order_items` | `order_id`, `product_id`, `quantity`, `price`, `total` | `order()`, `product()` |
| `Customer` | `ec_customers` | `user_id`, `first_name`, `last_name`, `email`, `total_orders`, `total_spent` | `user()`, `orders()`, `wallet()` |
| `Category` | `ec_categories` | `name`, `slug`, `parent_id`, `is_active` | `parent()`, `children()` |

### Pricing & Discounts
| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `Coupon` | `ec_coupons` | `code`, `type`, `value`, `min_order`, `usage_limit` |
| `CouponUse` | `ec_coupon_uses` | `coupon_id`, `order_id`, `customer_id` |
| `Currency` | `ec_currencies` | `code`, `symbol`, `exchange_rate_to_usd`, `is_default` |
| `TaxConfig` | `ec_tax_configs` | `name`, `rate`, `type`, `is_inclusive`, `is_active` |

### Customer Wallet & Loyalty
| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `Wallet` | `ec_wallets` | `customer_id`, `balance` |
| `WalletTransaction` | `ec_wallet_transactions` | `wallet_id`, `type`, `amount`, `balance_after` |
| `LoyaltyProgram` | `ec_loyalty_programs` | `name`, `points_per_unit`, `min_points_redeem` |
| `LoyaltyTransaction` | `ec_loyalty_transactions` | `customer_id`, `points`, `type` |
| `CustomerPoints` | `ec_customer_points` | `customer_id`, `program_id`, `balance` |

### Returns & Refunds
| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `ReturnRequest` | `ec_return_requests` | `order_id`, `status`, `type`, `reason` |
| `ReturnItem` | `ec_return_items` | `return_request_id`, `order_item_id`, `quantity` |
| `Refund` | `ec_refunds` | `order_id`, `amount`, `method`, `status` |
| `Review` | `ec_reviews` | `product_id`, `customer_id`, `rating`, `comment` |

### Product Variants & Media
| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `ProductVariant` | `ec_product_variants` | `product_id`, `sku`, `price`, `stock` |
| `ProductImage` | `ec_product_images` | `product_id`, `url`, `position`, `is_primary` |
| `Cart` | `ec_carts` | `customer_id`, `items`, `total` |

### Inventory (Cross-Module)
| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `Supplier` | `ec_suppliers` | `name`, `email`, `phone`, `payment_terms` |
| `PurchaseOrder` | `ec_purchase_orders` | `po_number`, `supplier_id`, `status`, `total` |
| `PurchaseOrderItem` | `ec_purchase_order_items` | `purchase_order_id`, `product_id`, `quantity` |
| `NotificationTemplate` | `ec_notification_templates` | `key`, `channel`, `subject`, `body` |

---

## 🔗 Module Task Log

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Ecommerce/module_task.md)
