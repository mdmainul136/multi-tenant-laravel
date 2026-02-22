# Cross-Border IOR Module — Laravel Architecture Plan
## Based on Full Audit of "A2Z Outlet Store" Supabase Codebase
> **Last Updated:** 2026-02-20 | Supabase → Laravel Migration Blueprint

---

## 1. Real Codebase Summary

The source project is **A2Z Outlet Store** — a Bangladesh-based IOR (Importer of Record) platform built on Supabase (PostgreSQL) + Deno Edge Functions. It allows customers to paste a product URL from Amazon/eBay/Walmart/Alibaba and have it sourced, imported, and delivered to their door in BDT.

| Metric | Count |
|--------|-------|
| Migration files | 28 |
| Edge Functions | 44 |
| Database tables (approx) | 73+ |
| Payment gateways | 4 (bKash, Nagad, SSLCommerz, Stripe) |
| Courier integrations | 5 (Pathao, Steadfast, RedX, FedEx, DHL) |
| Scraper providers | 2 (Apify/Oxylabs primary, fallback) |

---

## 2. All 44 Edge Functions → Laravel Mapping

### 2.1 Product Scraping & Import

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `scrape-foreign-product` | 1375 | `App\Services\Scraper\OxylabsScraperService` | Primary: Oxylabs API. Fallback: Apify actors. Supports Amazon/eBay/Walmart/Alibaba. Calculates BDT pricing (customs + shipping + margin). |
| `apify-scrape` | 1905 | `App\Services\Scraper\ApifyScraperService` | Full junglee~amazon-crawler support. Extracts reviews, A+ content, videos, variants. Logs to `import_logs`. Reads `international_shipping_settings` + `customs_rates` for pricing. |
| `apify-bestsellers` | - | `App\Services\Scraper\ApifyBestsellersService` | Syncs hot products from Apify actors |
| `apify-search` | - | `App\Services\Scraper\ApifySearchService` | Search Apify for product matches |
| `fetch-bestsellers` | - | `App\Services\Scraper\FetchBestsellersService` | Internal bestseller aggregation |
| `bulk-import-products` | - | `App\Jobs\BulkImportProductsJob` | Queue-based bulk import |
| `sync-foreign-products` | - | `App\Jobs\SyncForeignProductsJob` | Scheduled sync |
| `analyze-product-image` | - | `App\Services\AI\ProductImageAnalysisService` | AI image analysis |
| `compare-schema` | - | `App\Console\Commands\CompareSchema` | Dev utility |
| `database-migration-tool` | - | `App\Console\Commands\DatabaseMigrationTool` | Dev utility |
| `export-database` | - | `App\Console\Commands\ExportDatabase` | Dev utility |
| `migrate-storage-files` | - | `App\Console\Commands\MigrateStorageFiles` | Dev utility |

### 2.2 FX Rate / Pricing

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `get-exchange-rate` | 100 | `App\Services\FX\ExchangeRateService` | Primary: `open.er-api.com`. Fallback: `frankfurter.app`. Static fallback: 120 BDT/USD. |
| `update-exchange-rates` | 238 | `App\Jobs\UpdateExchangeRatesJob` (scheduled daily) | Fetches rate → recalculates all foreign product prices using `pricing_settings` JSON in `catalog_products.attributes`. Logs to `feed_sync_logs`. Calls `send-exchange-rate-notification`. |
| `bulk-recalculate-prices` | - | `App\Jobs\BulkRecalculatePricesJob` | Admin-triggered mass reprice |

**Pricing Formula:**
```
basePriceBdt = usdPrice × exchangeRate
customsDutyBdt = basePriceBdt × (customsRate / 100)     -- from customs_rates table
airShipping = weight × airRatePerKg                       -- from international_shipping_settings
finalPrice = ceil((basePriceBdt + customs + shipping) × (1 + profitMargin/100))
```

**Category → Customs Rate mapping** (auto-detected from product title):
- `cosmetics`, `electronics`, `footwear`, `clothing`, `accessories`, `sports`, `toys`, `general`

### 2.3 Payments

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `bkash-init` | 169 | `App\Services\Payment\BkashPaymentService::initiate()` | 3-step: grant token → create payment → redirect. **Credentials from `app_settings` DB, not .env**. |
| `bkash-callback` | 179 | `App\Services\Payment\BkashPaymentService::execute()` | Executes bKash payment. Updates `payment_transactions`. |
| `nagad-init` | 121 | `App\Services\Payment\NagadPaymentService` | ⚠️ placeholder only — needs RSA key pair. NOT production ready. |
| `sslcommerz-init` | 169 | `App\Services\Payment\SSLCommerzService::initiate()` | POST to `gwprocess/v4`. Credentials from `app_settings`. |
| `sslcommerz-ipn` | 175 | `App\Http\Controllers\Payment\SSLCommerzIpnController` | Validates with SSLCommerz API. Checks amount match. Updates `payment_transactions` + `orders`. |
| `stripe-checkout` | 190 | `App\Services\Payment\StripeCheckoutService` | Stripe Checkout Session. Default currency BDT. Supports BDT/USD/EUR/GBP/INR. |
| `stripe-webhook` | - | `App\Http\Controllers\Payment\StripeWebhookController` | Stripe event handler |

**Payment flow:** All gateways write to `payment_transactions` table (fields: `order_id`, `transaction_id`, `gateway`, `amount`, `currency`, `status`, `validation_id`, `bank_transaction_id`, `gateway_response` JSONB).

**All credentials stored in `app_settings` table:**
- `payment_bkash_app_key`, `payment_bkash_app_secret`, `payment_bkash_username`, `payment_bkash_password`, `payment_bkash_sandbox`
- `payment_sslcommerz_store_id`, `payment_sslcommerz_store_password`, `payment_sslcommerz_sandbox`
- `STRIPE_SECRET_KEY` (env variable)

### 2.4 Courier & Shipping

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `book-courier` | 395 | `App\Services\Courier\CourierBookingService` | BD domestic: **Pathao** + **Steadfast** + **RedX**. International: **FedEx** + **DHL**. Reads from `courier_configurations` table. |
| `track-shipment` | 290 | `App\Services\Courier\ShipmentTrackingService` | Tracks via **FedEx** + **DHL** + **UPS** APIs. Falls back to tracking URL. |
| `shipping-rates` | 425 | `App\Services\Courier\ShippingRateService` | Gets live rate quotes from FedEx/DHL/UPS. |

### 2.5 Email Marketing & Notifications

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `process-scheduled-emails` | 986 | `App\Jobs\ProcessScheduledEmailsJob` | **Resend API**. A/B testing (test phase → 2hr wait → auto-winner by open rate). Per-recipient personalisation. Bounce tracking. Unsubscribe management. Re-engagement campaigns with auto-generated discount codes. Deliverability scoring. |
| `send-order-notification` | 391 | `App\Mail\OrderNotification` | Templated order emails (confirmed/shipped/delivered). Reads from `email_templates` table. Has hardcoded fallback templates. |
| `send-exchange-rate-notification` | 186 | `App\Mail\ExchangeRateNotification` | Sends exchange rate update summary to `admin_notification_email` setting. |
| `send-auth-email` | - | `App\Mail\AuthEmail` | OTP / welcome email |
| `send-content-report` | - | `App\Mail\ContentReport` | Content audit report email |
| `send-product-notification` | - | `App\Mail\ProductNotification` | Product-related alerts |
| `send-task-notification` | - | `App\Mail\TaskNotification` | Task assignment notifications |
| `track-email-open` | - | `App\Http\Controllers\Email\TrackEmailOpenController` | 1px pixel tracker, updates `email_logs.opened_at` |
| `track-email-click` | - | `App\Http\Controllers\Email\TrackEmailClickController` | Link redirect tracker, updates `email_logs.clicked_at` |
| `email-preferences` | - | `App\Http\Controllers\Email\EmailPreferencesController` | Unsubscribe / manage prefs page |

### 2.6 Staff Task System

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `process-task-escalations` | 277 | `App\Jobs\ProcessTaskEscalationsJob` | Reads `task_escalation_settings`. Emails managers via Resend. Creates in-app `notifications`. Logs to `task_escalation_logs`. Increments `escalation_level` on `staff_tasks`. |
| `process-recurring-tasks` | 121 | `App\Jobs\ProcessRecurringTasksJob` | Daily/weekly/monthly recurrence. Creates new task instance from template task. Updates `next_recurrence_at`. |
| `process-task-reminders` | - | `App\Jobs\ProcessTaskRemindersJob` | Pre-due-date reminder emails |

### 2.7 AI Content Generation

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `generate-content` | 369 | `App\Services\AI\ContentGenerationService` | **Lovable AI Gateway** → Gemini 3 Flash. 8 types: `product_description`, `product_short_description`, `product_meta`, `product_features`, `product_benefits`, `product_ingredients`, `category_description`, `category_meta`, `content_quality` (score 0-100). Vision-capable for product images. |
| `generate-product-description` | - | `App\Services\AI\ProductDescriptionService` | Simpler description-only variant |
| `generate-logo` | - | `App\Services\AI\LogoGenerationService` | Logo image generation |

**In Laravel:** Replace `LOVABLE_API_KEY` + Lovable gateway with direct **OpenAI/Gemini API** key.

### 2.8 Chatbot

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `n8n-chat` | 348 | `App\Http\Controllers\ChatController` | Reads `chat_faqs` table. Checks `orders` + `foreign_orders` for order tracking. Optional **n8n webhook** forwarding. Responds in Bangla. |

### 2.9 Auth / Invoicing / Misc

| Supabase Function | Lines | Laravel Equivalent | Notes |
|---|---|---|---|
| `verify-auth-code` | 131 | `App\Http\Controllers\Auth\VerifyAuthCodeController` | `email_token` (from `email_verification_tokens`) + `2fa_code` (from `two_factor_codes`) flows. |
| `generate-invoice` | 469 | `App\Services\Invoice\InvoiceGenerationService` | HTML invoice for local + foreign orders. Reads `brand_settings` + `invoice_templates`. Template engine with `{{variable}}` and `{{#if variable}}` syntax. |

---

## 3. Database Tables (Complete List)

### 3.1 Core / Auth
| Table | Key Columns | Notes |
|---|---|---|
| `profiles` | `user_id`, `full_name`, `phone`, `email`, `email_verified`, `avatar_url` | Linked to Supabase auth |
| `user_roles` | `user_id`, `role` (admin/moderator/user) | ENUM type, has `has_role()` security definer function |
| `email_verification_tokens` | `user_id`, `email`, `token`, `expires_at`, `verified_at` | |
| `two_factor_codes` | `user_id`, `code`, `type`, `expires_at`, `used_at` | |

### 3.2 Products
| Table | Key Columns | Notes |
|---|---|---|
| `catalog_products` | `id`, `name`, `slug`, `price`, `compare_price`, `currency` (BDT), `product_type` (local/foreign), `attributes` JSONB, `facebook_sync_enabled`, `google_sync_enabled` | **Primary product table** |
| `product_categories` | `id`, `name`, `slug`, `parent_id`, `is_active` | Self-referential hierarchy |
| `product_feed_view` | view of catalog_products | For FB/Google feed |
| `google_merchant_feed` | view | google_sync_enabled = true |
| `facebook_catalog_feed` | view | facebook_sync_enabled = true |

### 3.3 Foreign Orders (IOR Core)
| Table | Key Columns | Notes |
|---|---|---|
| `foreign_product_orders` | `order_number` (FPO-YYYYMMDD-00001), `user_id`, `product_url`, `product_name`, `quantity`, `product_variant`, `source_marketplace` (amazon/alibaba/ebay/other), shipping fields, `estimated_price`, `final_price`, `advance_payment`, `remaining_payment`, `advance_paid`, `remaining_paid`, `order_status` | **Core IOR table**. Status: pending→sourcing→ordered→shipped→customs→delivered→cancelled |
| `foreign_orders` | Extended version used in chatbot | Also has `order_number`, `tracking_number` |
| `import_logs` | `url`, `marketplace`, `status`, `error_message`, `product_id`, `request_payload`, `response_data` | Scrape attempt audit trail |

### 3.4 Local Orders
| Table | Key Columns | Notes |
|---|---|---|
| `orders` | `order_number`, `status`, `total`, `items` JSONB, `shipping_*`, `payment_method`, `payment_status`, `tracking_number` | Local BD orders |
| `payment_transactions` | `order_id`, `transaction_id`, `gateway`, `amount`, `currency`, `status`, `validation_id`, `bank_transaction_id`, `card_type`, `card_brand`, `gateway_response` JSONB | All payment gateways share this table |

### 3.5 Pricing Configuration
| Table | Key Columns | Notes |
|---|---|---|
| `customs_rates` | `category`, `rate_percentage`, `is_active` | Per-category import duty % |
| `international_shipping_settings` | `shipping_method` (air/sea), `rate_per_kg`, `is_active` | Default: air=1500 BDT/kg, sea=400 BDT/kg |
| `courier_configurations` | `courier_code`, credentials (JSONB), `is_active` | Pathao/Steadfast/RedX/FedEx/DHL creds |
| `app_settings` | `key`, `value` | ALL config: payment keys, email settings, n8n URL, admin email |
| `brand_settings` | `setting_key`, `setting_value` | Store branding (name, logo, address) |

### 3.6 Email System (8 tables)
| Table | Key Columns | Notes |
|---|---|---|
| `scheduled_emails` | `template_key`, `subject`, `subject_variant_b`, `is_ab_test`, `ab_split_percentage`, `auto_winner_enabled`, `test_size_percentage`, `ab_phase`, `recipients` JSONB, `html_content`, `status` | Full A/B test engine |
| `email_templates` | `template_key`, `subject`, `html_content`, `is_active` | DB-stored templates |
| `email_logs` | `template_key`, `recipient_email`, `subject`, `status`, `resend_id`, `opened_at`, `clicked_at`, `bounce_type`, `delivery_status`, `ab_variant`, `order_id`, `foreign_order_id` | Full tracking |
| `email_preferences` | `email`, `all_emails`, `promotional_emails`, `newsletter`, `unsubscribed_at` | Per-email unsubscribe |
| `customer_engagement` | `email`, `engagement_score`, `engagement_tier` (champion/active/engaged/passive/at_risk/inactive/new), `is_valid`, `invalid_reason`, `total_bounces` | List hygiene |
| `reengagement_campaigns` | `name`, `trigger_tier`, `offer_type`, `offer_value`, `email_subject`, `email_content`, `max_sends_per_day`, `total_sent`, `last_run_at` | Win-back campaigns |
| `email_deliverability` | `date`, `total_sent`, `delivery_rate`, `bounce_rate`, `open_rate`, `click_rate`, `domain_reputation`, `spam_score` | Daily deliverability |
| `deliverability_alerts` | `alert_type`, `severity`, `title`, `message`, `metric_value`, `threshold_value` | Threshold-based alerts |

### 3.7 Staff Task System (9 tables)
| Table | Key Columns | Notes |
|---|---|---|
| `staff_tasks` | `title`, `description`, `priority` (low/medium/high/urgent), `status` (todo/in_progress/done/cancelled), `assigned_to`, `created_by`, `tags`, `is_recurring`, `recurrence_pattern` (daily/weekly/monthly), `recurrence_interval`, `next_recurrence_at`, `recurrence_end_date`, `parent_recurring_id`, `escalated_at`, `escalation_level` | Kanban + recurring |
| `task_escalation_settings` | `priority`, `escalation_hours`, `notify_roles`, `is_enabled` | Per-priority escalation config |
| `task_escalation_logs` | `task_id`, `escalation_level`, `notified_user_ids`, `reason` | Audit trail |
| `chat_faqs` | `question`, `answer`, `keywords`, `category`, `priority`, `view_count`, `is_active` | Chatbot FAQ |
| `invoice_templates` | `html_template`, `css_styles`, `header_html`, `footer_html`, `is_default`, `is_active` | Printable invoice templates |
| `discount_codes` | `code`, `discount_type` (percentage/fixed), `discount_value`, `campaign_id`, `maximum_uses`, `expires_at` | Single-use re-engagement codes |
| `notifications` | `user_id`, `type`, `title`, `message`, `data` JSONB, `is_read` | In-app notifications |
| `feed_sync_logs` | `feed_type` (facebook/google/exchange_rate_update/price_recalculation), `status`, `products_synced`, `error_message` | Cron job logs |

---

## 4. Laravel Service Architecture

### 4.1 Directory Structure
```
app/
├── Services/
│   ├── Scraper/
│   │   ├── OxylabsScraperService.php        # Primary scraper
│   │   ├── ApifyScraperService.php           # Fallback (junglee actor)
│   │   └── ProductPricingCalculator.php      # USD→BDT formula
│   ├── FX/
│   │   └── ExchangeRateService.php           # open.er-api.com + frankfurter
│   ├── Payment/
│   │   ├── BkashPaymentService.php           # 3-step tokenized flow
│   │   ├── NagadPaymentService.php           # ⚠️ incomplete upstream
│   │   ├── SSLCommerzService.php             # Already partially exists!
│   │   └── StripeCheckoutService.php
│   ├── Courier/
│   │   ├── CourierBookingService.php         # Pathao/Steadfast/RedX/FedEx/DHL
│   │   ├── ShipmentTrackingService.php       # Multi-carrier tracking
│   │   └── ShippingRateService.php           # Live rate quotes
│   ├── AI/
│   │   ├── ContentGenerationService.php      # 8 content types via OpenAI/Gemini
│   │   └── ProductImageAnalysisService.php
│   ├── Email/
│   │   └── EmailMarketingService.php         # A/B tests, campaigns, deliverability
│   ├── Invoice/
│   │   └── InvoiceGenerationService.php      # HTML invoice from DB template
│   └── Chat/
│       └── ChatbotService.php                # FAQ matching + order lookup
├── Jobs/
│   ├── UpdateExchangeRatesJob.php            # Daily cron → recalculate all prices
│   ├── ProcessScheduledEmailsJob.php         # Email queue processor
│   ├── ProcessTaskEscalationsJob.php         # Hourly escalation check
│   ├── ProcessRecurringTasksJob.php          # Daily recurring task spawner
│   ├── BulkImportProductsJob.php
│   └── SyncForeignProductsJob.php
├── Http/Controllers/
│   ├── Payment/
│   │   ├── BkashController.php
│   │   ├── SSLCommerzIpnController.php
│   │   └── StripeWebhookController.php
│   ├── Email/
│   │   ├── TrackEmailOpenController.php      # 1px pixel
│   │   ├── TrackEmailClickController.php     # Redirect tracker
│   │   └── EmailPreferencesController.php    # Unsubscribe page
│   ├── Auth/
│   │   └── VerifyAuthCodeController.php      # email_token + 2fa_code
│   └── ChatController.php
```

### 4.2 Scheduled Tasks (Laravel Scheduler)
```php
// app/Console/Kernel.php
$schedule->job(UpdateExchangeRatesJob::class)->daily();
$schedule->job(ProcessScheduledEmailsJob::class)->everyMinute();
$schedule->job(ProcessTaskEscalationsJob::class)->hourly();
$schedule->job(ProcessRecurringTasksJob::class)->daily();
$schedule->job(SyncForeignProductsJob::class)->hourly();
```

---

## 5. Database Migration Plan (PostgreSQL → MySQL)

### 5.1 Critical Schema Differences

| PostgreSQL (Supabase) | MySQL (Laravel) | Notes |
|---|---|---|
| `UUID PRIMARY KEY DEFAULT gen_random_uuid()` | `id uuid() primary key` or `bigIncrements` | Use Laravel UUIDs trait |
| `JSONB` columns | `json()` | MySQL JSON is fine |
| `TEXT[]` arrays | `json()` | Serialize arrays as JSON |
| `auth.users` foreign key | `users` table | Replace Supabase auth with Laravel Sanctum |
| `ENUM` types | `enum()` columns | Direct equivalent |
| `SECURITY DEFINER` functions | Laravel Policies | Replace PG security with Laravel RBAC |
| `TIMESTAMPTZ` | `timestamps()` | Laravel handles timezone |

### 5.2 Key Tables to Create (in order)

1. `users` (replaces Supabase `auth.users`)
2. `profiles` (linked to users)
3. `user_roles` (admin/moderator/user enum)
4. `app_settings` (key-value config store — CRITICAL, used by all payment gateways)
5. `brand_settings`
6. `product_categories`
7. `catalog_products` (with `attributes` JSON column for pricing_settings)
8. `customs_rates`
9. `international_shipping_settings`
10. `courier_configurations`
11. `foreign_product_orders`
12. `orders`
13. `payment_transactions`
14. `import_logs`
15. `feed_sync_logs`
16. `email_templates` + `email_logs` + `email_preferences` + `scheduled_emails`
17. `customer_engagement` + `reengagement_campaigns` + `email_deliverability` + `deliverability_alerts`
18. `staff_tasks` + task escalation tables
19. `chat_faqs`
20. `invoice_templates`
21. `discount_codes`
22. `notifications`
23. `email_verification_tokens` + `two_factor_codes`

### 5.3 `foreign_product_orders` Full Schema
```php
Schema::create('foreign_product_orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('order_number')->unique()->nullable(); // FPO-YYYYMMDD-00001
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->text('product_url');
    $table->string('product_name');
    $table->integer('quantity')->default(1);
    $table->string('product_variant')->nullable();
    $table->string('product_image_url')->nullable();
    $table->enum('source_marketplace', ['amazon','alibaba','ebay','other'])->nullable();
    // Shipping
    $table->string('shipping_full_name')->nullable();
    $table->string('shipping_phone')->nullable();
    $table->text('shipping_address')->nullable();
    $table->string('shipping_city')->nullable();
    // Pricing
    $table->decimal('estimated_price', 10, 2)->nullable();
    $table->decimal('final_price', 10, 2)->nullable();
    $table->decimal('advance_payment', 10, 2)->nullable();
    $table->decimal('remaining_payment', 10, 2)->nullable();
    $table->boolean('advance_paid')->default(false);
    $table->boolean('remaining_paid')->default(false);
    // Status: pending → sourcing → ordered → shipped → customs → delivered → cancelled
    $table->enum('order_status', ['pending','sourcing','ordered','shipped','customs','delivered','cancelled'])->default('pending');
    $table->timestamps();
});
```

### 5.4 Order Number Auto-Generation
```php
// In ForeignProductOrder model boot()
protected static function boot() {
    parent::boot();
    static::creating(function ($order) {
        $order->order_number = 'FPO-' . now()->format('Ymd') . '-' . str_pad(
            DB::table('foreign_product_orders')->count() + 1, 5, '0', STR_PAD_LEFT
        );
    });
}
```

---

## 6. Migration from Supabase Auth

Supabase auth is replaced by **Laravel Sanctum** + custom email verification:

| Supabase | Laravel |
|---|---|
| `auth.users` | `users` + `profiles` tables |
| Supabase email OTP | `send-auth-email` → `App\Mail\OtpMail` |
| `verify-auth-code` (email_token) | `VerifyAuthCodeController` with `email_verification_tokens` table |
| `verify-auth-code` (2fa_code) | Same controller, `two_factor_codes` table |
| Supabase RLS policies | Laravel Policies + Gates |
| `has_role()` function | `App\Models\User::hasRole()` method |

---

## 7. External API Keys Required

| Service | Used For | ENV Variable |
|---|---|---|
| Oxylabs | Primary product scraper | `OXYLABS_USERNAME`, `OXYLABS_PASSWORD` |
| Apify | Fallback scraper + bestsellers | `APIFY_API_TOKEN` |
| open.er-api.com | Exchange rate (free, no key) | — |
| Resend | All transactional emails | `RESEND_API_KEY` |
| bKash | Payment | Stored in `app_settings` DB |
| SSLCommerz | Payment | Stored in `app_settings` DB |
| Stripe | Payment | `STRIPE_SECRET_KEY` |
| Pathao | Local courier | Stored in `courier_configurations` DB |
| Steadfast | Local courier | Stored in `courier_configurations` DB |
| RedX | Local courier | Stored in `courier_configurations` DB |
| FedEx | International courier | Stored in `courier_configurations` DB |
| DHL | International courier | Stored in `courier_configurations` DB |
| OpenAI/Gemini | Content generation | `AI_API_KEY` (replaces Lovable gateway) |
| n8n (optional) | Chatbot AI | Stored in `app_settings` DB |

---

## 8. IOR Customer Journey Flow

```
1. Customer pastes Amazon/eBay URL
   └── POST /api/scrape-product → ApifyScraperService
       ├── Detect marketplace
       ├── Run Oxylabs (primary) or Apify (fallback)
       ├── Extract: title, images, price (USD), variants, reviews
       ├── Calculate BDT price: USD × rate + customs + shipping + margin
       └── Return product data with price estimate

2. Customer reviews & places order
   └── POST /api/foreign-orders → ForeignProductOrderController
       ├── Create order (status: pending)
       ├── Calculate 50% advance payment
       └── Redirect to payment gateway (bKash / SSLCommerz / Stripe)

3. Payment callback
   └── POST /payment/bkash/callback → BkashPaymentService::execute()
       ├── Execute payment with token
       ├── Update payment_transactions (status: paid)
       └── Update order (advance_paid: true, status: sourcing)

4. Admin sources & orders from Amazon
   └── Order placed on Amazon → status: ordered

5. International shipping
   └── Package arrives at BD port → status: customs
       └── CourierBookingService → Pathao/Steadfast booking → status: shipped

6. Delivery
   └── status: delivered
       └── Trigger send-order-notification (delivered email)
       └── Collect remaining 50% payment

7. Invoice
   └── GET /api/invoice/{orderId}?type=foreign → InvoiceGenerationService
       └── Returns HTML invoice for printing
```

---

## 9. Implementation Phases

### Phase 1 — Core IOR (4 weeks)
- [ ] Database migrations (73+ tables)
- [ ] Supabase auth → Laravel Sanctum migration
- [ ] `ApifyScraperService` (URL paste → BDT price)
- [ ] `ExchangeRateService` (daily cron)
- [ ] `ForeignProductOrderController` (CRUD + status flow)
- [ ] `SSLCommerzService` (already partially exists!)
- [ ] `BkashPaymentService` (3-step flow)
- [ ] `InvoiceGenerationService` (HTML invoice)
- [ ] Basic admin panel (orders, products)

### Phase 2 — Shipping & Tracking (2 weeks)
- [ ] `CourierBookingService` (Pathao + Steadfast + RedX)
- [ ] `ShipmentTrackingService` (FedEx + DHL + UPS)
- [ ] `ShippingRateService` (live quotes)
- [ ] International shipping settings admin

### Phase 3 — Email Marketing (2 weeks)
- [ ] Resend integration
- [ ] `ProcessScheduledEmailsJob` (A/B testing engine)
- [ ] `ProcessTaskEscalationsJob`
- [ ] Email templates admin
- [ ] Deliverability monitoring

### Phase 4 — AI & Chatbot (1 week)
- [ ] `ContentGenerationService` (8 AI content types)
- [ ] `ChatbotService` (FAQ + order tracking)
- [ ] Bulk product import

### Phase 5 — Advanced (ongoing)
- [ ] Facebook/Google feed sync
- [ ] Nagad payment (needs RSA key pair)
- [ ] Apify bestsellers sync
- [ ] Re-engagement campaigns

---

## 10. Important Implementation Notes

1. **`app_settings` table is critical** — All payment gateway credentials, email settings, n8n URL, admin email are stored here. Create this table first with a seeder for defaults.

2. **`pricing_settings` in `attributes` JSONB** — The `catalog_products.attributes` column contains a `pricing_settings` key: `{exchangeRate, shippingCost, customsFee, profitMargin, shippingMethod, productWeight, airRatePerKg, seaRatePerKg}`. This is used by the daily price recalculation job.

3. **Nagad is NOT production-ready** — The upstream code is a placeholder. Needs RSA key pair implementation.

4. **Resend is the email provider** — Not SMTP. All transactional + marketing emails use Resend API (`POST https://api.resend.com/emails`).

5. **Two separate order tables** — `orders` (local BD products) and `foreign_product_orders` (IOR). Both appear in chatbot order lookup and invoice generation.

6. **Order number format:** Local orders: `ORD-YYYYMMDD-XXXXX`, Foreign orders: `FPO-YYYYMMDD-XXXXX`, Foreign product orders: `FO-...`

7. **SSLCommerz already partially exists** in the Laravel project (`test_sslcommerz.php`) — Phase 1 will be faster.

8. **Apify actor priority:** `junglee~amazon-crawler` (primary for Amazon — rich data: reviews, A+ content, hi-res images, videos) → `apify~e-commerce-scraping-tool` (universal fallback).
