# 🧾 Zatca Module — Complete Reference

> **Module Key**: `zatca` | Saudi Arabia e-invoicing (ZATCA Phase 2) compliance.
> QR code generation, invoice signing, and regulatory submission.

## 📂 Directory Structure

```
app/Modules/Zatca/
├── module.json
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Zatca — 1 model)

| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `ZatcaInvoice` | `zatca_invoices` | `invoice_number`, `order_id`, `xml_content`, `qr_code`, `hash`, `signed_xml`, `status`, `submitted_at` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Zatca/module_task.md)
