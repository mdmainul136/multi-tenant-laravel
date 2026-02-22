---
description: Rules and workflow for working on the Tracking module
---

# Tracking Module Workflow

> Use this workflow whenever you are working on the Tracking/Analytics module.

## 📌 Module Rules (MUST Follow)

### Rule 1: Always Read Context First
1. Read `app/Modules/Tracking/DOCUMENTATION.md`.
2. Read `app/Modules/Tracking/module_task.md`.

### Rule 2: Model Standards
- All Tracking models MUST extend `TenantBaseModel`.
- All models MUST be in `app/Models/Tracking/` namespace.
- Table names MUST use `tracking_` prefix.

### Rule 3: Folder Structure
```
app/Modules/Tracking/
├── Actions/          # IngestEventAction, ProcessEventAction, RouteEventAction
├── Console/          # ProcessDlqCommand, PruneEventsCommand, etc.
├── Controllers/      # TrackingController, SignalsController, GatewayController, etc.
├── DTOs/
├── Jobs/             # ProcessEventJob, RetryDlqJob
├── Services/         # 19 services + 5 channel adapters
│   └── Channels/     # FacebookChannel, GoogleChannel, TikTokChannel, etc.
├── routes/api.php
└── module.json
```

### Rule 4: Event Ingestion Pipeline
- All events MUST flow through `IngestEventAction` → `ProcessEventAction` → `RouteEventAction`.
- Events are validated via `EventValidationService` schema checks.
- Events are enriched via `EventEnrichmentService` (user agent parsing, geo, device).
- NEVER route events directly to destinations — always go through the pipeline.

### Rule 5: Consent & Privacy (CRITICAL)
- GDPR/CCPA compliance is managed via `ConsentManagementService`.
- Events MUST be checked against user consent before processing.
- PII data MUST be filtered via `DataFilterService` before sending to destinations.
- Consent records are stored in `TrackingConsent` model.
- If consent is not granted, the event MUST NOT be sent to third-party destinations.

### Rule 6: Destination Routing
- Events are fanned out to multiple destinations via `DestinationService`.
- Each destination has its own configuration in `TrackingDestination`.
- Channel adapters: `FacebookChannel`, `GoogleChannel`, `TikTokChannel`, `SnapchatChannel`, `TwitterChannel`.
- Failed events go to Dead Letter Queue (`TrackingDlq`).
- Use `RetryQueueService` for retry logic.

### Rule 7: Tag Management
- Tags are managed via `TagManagementService`.
- Tags belong to containers (`TrackingContainer`).
- Each tag has trigger configuration (`trigger_config` JSON).
- Tags can be enabled/disabled individually.

### Rule 8: Channel Health Monitoring
- `ChannelHealthService` monitors destination uptime.
- Health records stored in `TrackingChannelHealth`.
- Metrics: `latency_ms`, `error_rate`, `status`.
- Unhealthy channels should be auto-paused.

### Rule 9: Attribution
- Cross-channel attribution via `AttributionService`.
- Attribution models: `last_click`, `first_click`, `linear`, `time_decay`.
- Attribution records stored in `TrackingAttribution`.
- Session-based attribution using `session_id`.

### Rule 10: Usage Metering
- `TrackingUsageService` meters events processed per day.
- Usage records in `TrackingUsage`: `events_processed`, `events_failed`, `bandwidth_mb`.
- Usage limits can be enforced per tenant plan.

---

## 🔄 Step-by-Step Workflow

// turbo-all

### Step 1-6: Follow standard `/module-maintenance` workflow steps.
