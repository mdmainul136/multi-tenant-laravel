---
description: Rules and workflow for working on the Notifications module
---

# Notifications Module Workflow

> Use this workflow whenever you are working on the Notifications module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
1. Read `app/Modules/Notifications/DOCUMENTATION.md`.
2. Read `app/Modules/Notifications/module_task.md`.

### Rule 2: Model Standards
- All Notification models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Notifications/` namespace.
- Table names: `notification_templates`, `store_notifications`.

### Rule 3: Folder Structure
```
app/Modules/Notifications/
├── Actions/          # SendNotificationAction, RenderTemplateAction
├── Controllers/      # NotificationController
├── DTOs/
├── routes/api.php
└── module.json
```

### Rule 4: Template System
- Templates use `key` as unique identifier (e.g., `order.confirmed`, `leave.approved`).
- Templates support variable substitution: `{{customer_name}}`, `{{order_number}}`.
- Channel types: `email`, `sms`, `push`, `in_app`, `whatsapp`.
- Templates can be customized per tenant.

### Rule 5: Notification Dispatch
- Always use `SendNotificationAction` — NEVER send directly.
- The action resolves the template, renders variables, and dispatches to the correct channel.
- In-app notifications are stored in `StoreNotification`.
- `StoreNotification.read_at` is null until the user reads it.

### Rule 6: Cross-Module Integration
- Ecommerce: Order status changes → `order.status_changed` template.
- HRM: Leave approval → `leave.approved` template.
- IOR: Shipment updates → `shipment.status_changed` template.
- Marketing: Campaign messages go through Marketing module (NOT Notifications).

---

// turbo-all
### Step 1-6: Follow standard `/module-maintenance` workflow steps.
