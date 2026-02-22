# 🔔 Notifications Module — Complete Reference

> **Module Key**: `notifications` | Multi-channel notification system.
> Templates, email/SMS/push, and store-level notification management.

## 📂 Directory Structure

```
app/Modules/Notifications/
├── module.json
├── Actions/
│   ├── SendNotificationAction.php      # Dispatch across channels
│   └── RenderTemplateAction.php        # Variable substitution
├── Controllers/
│   └── NotificationController.php      # Notification CRUD & sending
├── DTOs/ (1 file)
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Notifications — 2 models)

| Model | Table | Key Fields |
| :--- | :--- | :--- |
| `NotificationTemplate` | `notification_templates` | `key`, `channel`, `subject`, `body`, `variables`, `is_active` |
| `StoreNotification` | `store_notifications` | `type`, `title`, `message`, `data`, `read_at`, `user_id` |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Notifications/module_task.md)
