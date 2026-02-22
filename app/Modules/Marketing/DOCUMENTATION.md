# 📣 Marketing Module — Complete Reference

> **Module Key**: `marketing` | Email/SMS campaigns, A/B testing, audience segmentation.

---

## 📂 Directory Structure

```
app/Modules/Marketing/
├── module.json
├── Actions/
│   ├── LaunchCampaignAction.php        # Campaign activation
│   └── TrackCampaignEventAction.php    # Event tracking per campaign
├── Controllers/
│   └── MarketingController.php         # Campaign CRUD
├── DTOs/ (1 file)
├── Services/
│   └── CampaignService.php             # Campaign logic
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Marketing — 5 models)

| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `Campaign` | `mkt_campaigns` | `name`, `type`, `channel`, `status`, `scheduled_at`, `sent_count`, `open_rate`, `click_rate` | `logs()`, `variants()` |
| `CampaignLog` | `mkt_campaign_logs` | `campaign_id`, `recipient`, `channel`, `status`, `sent_at`, `opened_at`, `clicked_at` | `campaign()` |
| `CampaignVariant` | `mkt_campaign_variants` | `campaign_id`, `variant_name`, `subject`, `body`, `send_percentage` | `campaign()` |
| `MarketingAudience` | `mkt_audiences` | `name`, `filters`, `member_count`, `is_dynamic` | — |
| `MarketingTemplate` | `mkt_templates` | `name`, `channel`, `subject`, `body`, `variables` | — |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Marketing/module_task.md)
