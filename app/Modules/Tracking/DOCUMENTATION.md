# 📡 Tracking Module — Complete Reference

> **Module Key**: `tracking` | Server-side event tracking & analytics.
> Equivalent of Google Tag Manager + Facebook CAPI + multi-destination event routing.

---

## 📂 Directory Structure

```
app/Modules/Tracking/
├── module.json
├── Actions/
│   ├── IngestEventAction.php           # Event ingestion pipeline
│   ├── ProcessEventAction.php          # Event processing & validation
│   └── RouteEventAction.php            # Fan-out to destinations
├── Console/
│   ├── ProcessDlqCommand.php           # Dead-letter queue processor
│   ├── PruneEventsCommand.php          # Old event cleanup
│   ├── SyncDestinationsCommand.php     # Destination health sync
│   └── TrackingReportCommand.php       # Analytics report generator
├── Controllers/
│   ├── DiagnosticsController.php       # System health & debug
│   ├── GatewayController.php           # Event ingestion endpoint
│   ├── InfrastructureController.php    # Container/proxy management
│   ├── ProxyController.php             # Server-side proxy
│   ├── SignalsController.php           # Signals gateway API
│   └── TrackingController.php          # Main tracking dashboard
├── DTOs/ (1 file)
├── Jobs/
│   ├── ProcessEventJob.php             # Async event processing
│   └── RetryDlqJob.php                # DLQ retry job
├── Services/ (19 services + 5 channels)
│   ├── AttributionService.php          # Cross-channel attribution
│   ├── ChannelHealthService.php        # Channel uptime monitoring
│   ├── ConsentManagementService.php    # GDPR/CCPA compliance
│   ├── DataFilterService.php           # PII filtering & redaction
│   ├── DatasetQualityService.php       # Data quality scoring
│   ├── DestinationService.php          # Multi-destination routing
│   ├── DirectIntegrationService.php    # Direct API integrations
│   ├── DockerOrchestratorService.php   # Container orchestration
│   ├── EventEnrichmentService.php      # Event context enrichment
│   ├── EventValidationService.php      # Schema validation
│   ├── FieldMappingService.php         # Field transformation
│   ├── MetaCapiService.php             # Facebook Conversions API
│   ├── PowerUpService.php              # Power-up extensions
│   ├── RetryQueueService.php           # Failed event retry logic
│   ├── SignalsGatewayService.php       # Unified signal processing
│   ├── TagManagementService.php        # Tag container management
│   ├── TrackingAnalyticsService.php    # Analytics computations
│   ├── TrackingProxyService.php        # Proxy configuration
│   ├── TrackingUsageService.php        # Usage metering
│   └── Channels/
│       ├── FacebookChannel.php         # FB Pixel/CAPI
│       ├── GoogleChannel.php           # GA4/GTM
│       ├── TikTokChannel.php           # TikTok Events API
│       ├── SnapchatChannel.php         # Snap CAPI
│       └── TwitterChannel.php          # Twitter CAPI
└── routes/
    └── api.php
```

## 🗄️ Data Models (app/Models/Tracking — 9 models)

| Model | Table | Key Fields | Relationships |
| :--- | :--- | :--- | :--- |
| `TrackingEventLog` | `tracking_event_logs` | `event_name`, `event_data`, `source`, `ip_address`, `user_agent` | — |
| `TrackingContainer` | `tracking_containers` | `name`, `type`, `config`, `is_active`, `snippet_code` | `tags()`, `destinations()` |
| `TrackingDestination` | `tracking_destinations` | `container_id`, `type`, `name`, `config`, `is_active` | `container()` |
| `TrackingTag` | `tracking_tags` | `container_id`, `name`, `type`, `trigger_config`, `is_active` | `container()` |
| `TrackingConsent` | `tracking_consents` | `user_id`, `consent_type`, `granted`, `ip_address`, `expires_at` | — |
| `TrackingDlq` | `tracking_dlq` | `event_id`, `destination_id`, `error`, `payload`, `retry_count`, `max_retries` | `destination()` |
| `TrackingAttribution` | `tracking_attributions` | `session_id`, `channel`, `source`, `medium`, `campaign`, `conversion_value` | — |
| `TrackingChannelHealth` | `tracking_channel_health` | `channel`, `status`, `latency_ms`, `error_rate`, `last_checked_at` | — |
| `TrackingUsage` | `tracking_usage` | `date`, `events_processed`, `events_failed`, `bandwidth_mb` | — |

---

See [module_task.md](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Modules/Tracking/module_task.md)
