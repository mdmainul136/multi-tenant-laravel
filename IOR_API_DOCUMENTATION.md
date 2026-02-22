# IOR (Cross-Border) Frontend API Documentation

This documentation covers the API endpoints for the **Cross-Border IOR (Importer of Record)** module. This module allows customers to scrape products from international marketplaces (Amazon, eBay, etc.) and purchase them in BDT with automated customs and shipping calculations.

## Base URL
`{{base_url}}/api/ior`

## Authentication
Most routes require a **Bearer Token** and a **X-Tenant-Id** header.
- `Authorization: Bearer <token>`
- `X-Tenant-Id: <tenant_id>`

---

## 1. Product Scraper & Quotes

### Scrape Product URL
Fetch live product data and a BDT price quote from a marketplace URL.
- **Endpoint:** `POST /scrape`
- **Body:**
  ```json
  {
    "url": "https://www.amazon.com/dp/B08N5KWB9H",
    "quantity": 1,
    "shipping_method": "air" 
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "product": { "title": "...", "price_usd": 99.99, "marketplace": "amazon", ... },
      "pricing": {
        "exchange_rate": 121.50,
        "estimated_price_bdt": 15400,
        "advance_amount": 7700,
        "customs_fee_bdt": 1200,
        "shipping_cost_bdt": 1500
      }
    }
  }
  ```

### Manual Price Quote
Get a quick BDT quote without scraping.
- **Endpoint:** `POST /quote`
- **Body:**
  ```json
  {
    "price_usd": 100,
    "weight_kg": 0.5,
    "product_title": "Laptop Sleeve",
    "shipping_method": "air"
  }
  ```

---

## 2. Order Management (Unified)

Orders containing IOR products should be sent to the main Ecommerce order endpoint, but you can also use the IOR-specific one.

### Place IOR Order
- **Endpoint:** `POST /orders`
- **Body:** (Same as Ecommerce orders but with IOR specific payload)
  ```json
  {
    "customer_id": 1,
    "items": [
      {
        "product_id": 50, 
        "quantity": 1
      }
    ],
    "shipping_method": "air",
    "payment_method": "nagad"
  }
  ```
- **Note:** If `product_id` refers to a product with `product_type: foreign`, the system treats it as an IOR order.

### Get My IOR Orders
- **Endpoint:** `GET /orders`
- **Query Params:** `?status=pending`, `?search=IOR-2024`

---

## 3. Payments

### Initiate Nagad Payment
- **Endpoint:** `POST /payment/nagad/initiate`
- **Body:**
  ```json
  {
    "order_id": 123,
    "payment_type": "advance" 
  }
  ```
- **Response:** `redirect_url` provided by Nagad.

### Check Payment Status
Unified endpoint to check status for any gateway.
- **Endpoint:** `GET /payment/status/{orderId}`

---

## 4. Catalog & AI

### Browse Scraped Catalog
Browse products that have been previously imported/scraped by admins.
- **Endpoint:** `GET /catalog`
- **Query Params:** `?search=nike`, `?marketplace=amazon`, `?per_page=20`

### AI Image Analysis (Vision)
Send an image URL of a product to get details back.
- **Endpoint:** `POST /ai/analyze-image`
- **Body:** `{ "image_url": "..." }`

---

## 5. Invoices

### View Invoice
- **Endpoint:** `GET /invoices/{id}`
- **Response:** Returns JSON containing both raw data and the pre-rendered HTML invoice.

### Download/Print Invoice
- **Endpoint:** `GET /invoices/{id}/download`
- **Response:** Raw HTML optimized for printing/PDF generation.

---

## Order Status Flow
1. `pending`: Order placed, waiting for advance payment.
2. `sourcing`: Payment received, admin is buying the product from source.
3. `ordered`: Product bought from source marketplace.
4. `shipped`: Product in transit to Bangladesh.
5. `customs`: Product at Bangladesh customs.
6. `delivered`: Product delivered to customer.
7. `cancelled`: Order cancelled.
