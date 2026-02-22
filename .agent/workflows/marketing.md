---
description: Rules and workflow for working on the Marketing module
---

# Marketing Module Workflow

> Use this workflow whenever you are working on the Marketing module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
1. Read `app/Modules/Marketing/DOCUMENTATION.md`.
2. Read `app/Modules/Marketing/module_task.md`.

### Rule 2: Model Standards
- All Marketing models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Marketing/` namespace.
- Table names MUST use `mkt_` prefix (e.g., `mkt_campaigns`, `mkt_audiences`).

### Rule 3: Folder Structure
```
app/Modules/Marketing/
├── Actions/          # LaunchCampaignAction, TrackCampaignEventAction
├── Controllers/      # MarketingController
├── DTOs/
├── Services/         # CampaignService
├── routes/api.php
└── module.json
```

### Rule 4: Campaign Lifecycle
- Campaign statuses:
  ```
  draft → scheduled → sending → completed
                               → failed
                               → paused
  ```
- Campaign types: `email`, `sms`, `push`, `whatsapp`.
- Channels: `email`, `sms`, `push_notification`, `whatsapp`.
- Use `LaunchCampaignAction` to activate campaigns.

### Rule 5: A/B Testing
- Campaigns support A/B variants via `CampaignVariant`.
- Each variant has `send_percentage` (total across variants MUST = 100%).
- Variant tracking: `subject`, `body`, `open_rate`, `click_rate`.
- Winner is determined by open rate or click rate after test period.

### Rule 6: Audience Segmentation
- `MarketingAudience` defines target groups.
- `filters` is a JSON field with filter rules (e.g., `{"total_spent": {">": 5000}}`).
- Dynamic audiences re-evaluate membership on each campaign send.
- `member_count` is cached and refreshed periodically.

### Rule 7: Campaign Logging
- Every sent message MUST be logged in `CampaignLog`.
- Log tracks: `sent_at`, `opened_at`, `clicked_at`, `bounced_at`.
- Aggregate stats are updated: `sent_count`, `open_rate`, `click_rate`.

### Rule 8: Templates
- Reusable templates stored in `MarketingTemplate`.
- Templates support variable substitution: `{{customer_name}}`, `{{order_total}}`.
- Channel-specific templates (email has HTML, SMS has plain text).

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1-6: Follow standard `/module-maintenance` workflow steps.
