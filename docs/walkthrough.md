# Tenant Database Isolation, Quotas & Analytics — Walkthrough

## Summary

Implemented a complete database isolation system where each tenant gets:
- A **restricted MySQL user** that can only access its own database
- A **storage quota** based on their plan (Starter 10GB / Business 15GB / Enterprise 20GB)
- **Analytics APIs** showing DB usage, per-table breakdown, and growth trends
- **Automated hourly stats collection** via scheduler

---

## Files Created / Modified

### New Files (10)

| File | Purpose |
|---|---|
| [create_tenant_database_plans_table.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/database/migrations/master/2026_02_19_160000_create_tenant_database_plans_table.php) | Storage tier definitions |
| [create_tenant_database_stats_table.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/database/migrations/master/2026_02_19_160100_create_tenant_database_stats_table.php) | Periodic usage snapshots |
| [add_database_plan_to_tenants.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/database/migrations/master/2026_02_19_160200_add_database_plan_to_tenants.php) | Adds plan + credentials columns |
| [TenantDatabasePlan.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Models/TenantDatabasePlan.php) | Plan model |
| [TenantDatabaseStat.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Models/TenantDatabaseStat.php) | Stats snapshot model |
| [TenantDatabaseIsolationService.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/TenantDatabaseIsolationService.php) | MySQL user creation, quota checks |
| [TenantDatabaseAnalyticsService.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/TenantDatabaseAnalyticsService.php) | INFORMATION_SCHEMA analytics |
| [TenantDatabaseController.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Http/Controllers/Api/TenantDatabaseController.php) | 4 API endpoints |
| [CollectDatabaseStats.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Console/Commands/CollectDatabaseStats.php) | Artisan command |
| [DatabasePlanSeeder.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/database/seeders/DatabasePlanSeeder.php) | Seeds 3 default plans |

### Modified Files (4)

| File | Change |
|---|---|
| [Tenant.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Models/Tenant.php) | Added plan/stats relationships, fillable fields, encrypted password cast |
| [DatabaseManager.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/DatabaseManager.php) | Auto-creates isolated MySQL user on DB creation |
| [api.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/routes/api.php) | Added 4 `/database/*` routes |
| [console.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/routes/console.php) | Registered hourly scheduler |

---

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/database/analytics` | Usage stats + quota + alerts |
| GET | `/api/database/tables` | Per-table size breakdown |
| GET | `/api/database/growth?days=30` | Historical growth trend |
| GET | `/api/database/plans` | Available storage plans |

---

## Migration Race Condition Fix

Implemented a robust fix to prevent duplicate migrations when multiple requests attempt to subscribe a tenant to the same module simultaneously.

### 1. Database Constraint
Added a composite unique index on the `module_migrations` table:
- **Columns:** `tenant_database`, `module_key`, `migration_file`
- **Migration:** `2026_02_19_183500_add_unique_constraint_to_module_migrations.php`

### 2. Row Locking & Transactions
Updated [ModuleMigrationManager.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/ModuleMigrationManager.php):
- **Transactions:** Wrapped the entire migration process in a database transaction on the master connection.
- **Locking:** Used `lockForUpdate()` (SQL `FOR UPDATE`) on the `module_migrations` table for the specific tenant and module. This ensures only one process can run migrations for that combination at a time.
- **Graceful Handling:** Added a catch block for `QueryException` to handle unique constraint violations (race conditions where locking might be bypassed or fail) by logging a warning instead of a hard crash.

---

## Production & Enterprise Enhancements

Implemented high-level SaaS features to ensure system reliability, data safety, and enterprise scalability.

### 1. Production Safety (Kill-Switch)
- **Status Enum Extension:** Updated `tenants.status` to include `active`, `suspended`, `billing_failed`, and `terminated`.
- **Middleware Guard:** [IdentifyTenant.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Http/Middleware/IdentifyTenant.php) now automatically blocks requests for any tenant not in `active` status with a 403 Forbidden response.

### 2. Job Context Safety (Queue Safety)
- **Trait:** [TenantAware.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Traits/TenantAware.php)
- **Functionality:** Provides a standardized way for background jobs to switch to the correct tenant database context before execution. Prevents data corruption during queue processing.

### 3. Non-Destructive Rollbacks (Archiving)
- **Safety Fix:** Modified [ModuleMigrationManager.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/ModuleMigrationManager.php) to rename tables with an `_archived_` prefix and timestamp instead of dropping them during module unsubscription. 
- **Data Preservation:** Ensures that customer data is never deleted without a manual purge.

### 4. Enterprise Observability & Versioning
- **Versioning:** Added `module_version` to `tenant_modules` tracking for controlled module upgrades.
- **Observability:** Extended `tenant_database_stats` with:
    - `slow_query_count`: Identifies performance bottlenecks.
    - `write_operation_count`: Tracks data volatility.
    - `top_tables_by_growth`: JSON column for growth trend analysis.

### 5. Tenant Backup Service
- **Service:** [TenantBackupService.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/TenantBackupService.php)
- **Purpose:** Automatically triggers `mysqldump` (simulated for now) before critical events like unsubscribing a module or deleting a tenant. Backups are stored in tenant-isolated directories.

---

## Multi-tenant Billing Enforcement

Automated systems to protect the platform from unauthorized usage and ensure revenue collection.

### 1. Module Access Control
- **Middleware:** [CheckModuleAccess.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Http/Middleware/CheckModuleAccess.php)
- **Logic:** Protects module-specific routes (e.g., Ecommerce) by verifying an active subscription. Returns `402 Payment Required` if access is denied.

### 2. Quota Enforcement
- **Middleware:** [EnforceDatabaseQuota.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Http/Middleware/EnforceDatabaseQuota.php)
- **Logic:** Intercepts write operations (POST, PUT, PATCH). Blocks the request with a `403 Forbidden` if the tenant has exceeded their storage (MB) or table count limits.

### 3. Automated Status Management
- **Service:** [BillingEnforcementService.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/BillingEnforcementService.php)
- **Automatic Suspension:** Checks for invoices overdue by more than 3 days. Automatically updates tenant status to `billing_failed`, triggering the global access kill-switch.
- **Auto-Restoration:** Automatically restores tenant to `active` once all overdue invoices are paid.
- **Scheduler:** [console.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/routes/console.php) runs this check daily at midnight.

---

## Critical Safety & Isolation (Built-in)

Directly addressing critical gaps to ensure production-grade reliability and data integrity.

### 1. Dual-Database Isolation (Core Architecture)
- **Master DB (`mysql`):** Strictly manages metadata, billing, and module subscriptions. Models like `Tenant`, `Invoice`, and `Module` are hard-coded to this connection.
- **Tenant DB (`tenant_dynamic`):** Isolated operational data. Identification middleware and background jobs switch the **default** connection dynamically.

### 2. Isolated MySQL User per Tenant (Deep Security)
- **Granular Permissions:** Every tenant now has a dedicated MySQL user (managed by `TenantDatabaseIsolationService`).
- **Blast Radius:** A breach of one tenant's credentials cannot access another tenant's database because the user only has `GRANT` permissions on one specific database name.
- **Dynamic Identification:** `DatabaseManager` now fetches these isolated credentials from the encrypted master record and applies them at runtime.

### 2. Global Queue Guard (Job Context Safety)
- **Automation:** [AppServiceProvider.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Providers/AppServiceProvider.php) now contains a global listener.
- **Safety:** Automatically detects `TenantAware` jobs and switches to the correct database context before execution. Prevents the "Wrong DB write" risk in background processing.

### 3. Non-Destructive Rollbacks (Archive-on-Rollback)
- **Data Protection:** [ModuleMigrationManager.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/ModuleMigrationManager.php) is now programmed to **never drop data**.
- **The Mechanism:** Before running a migration's `down()` method, the system scans the migration file for table names (both Raw SQL and Laravel Schema syntax) and renames them to `_archived_table_timestamp`.
- **Gap Closed:** This addresses the critical risk of accidental data deletion during module unsubscription while still allowing for a lean, on-demand database structure.

### 4. Raw SQL Core Migrations (Stability by Design)
- **Zero Drift:** Essential tenant tables (`users`, `personal_access_tokens`) are initialized via [DatabaseManager.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/DatabaseManager.php) using Raw SQL instead of framework Blueprints.
- **Identical Schema:** Guarantees that every tenant starts with an identical base schema, avoiding long-term maintenance baggage.

### 5. Module-Level Migration Engine (Enterprise Ready)
- **On-Demand:** Migrations for specific features (e.g., Ecommerce) are only run when a tenant subscribes, keeping the database lean.
- **Safe Rollbacks:** Enabled by default. Tenants can unsubscribe freely without fear of data loss or schema pollution.

### 6. Provisioning Atomicity (Gap 4 Fix)
- **State Machine:** Tenant creation follows `pending` → `db_created` → `migrated` → `active`.
- **Failure Resilience:** If a setup fails at any point, the tenant is marked as `failed`, enabling automated cleanup via `php artisan tenant:cleanup-provisioning`.

### 7. Enterprise Observability (Observability Depth)
- **Telemetry:** Now tracks `slow_query_count` and `write_operation_count` via the `INFORMATION_SCHEMA` and `performance_schema`.
- **Drift Detection:** [TenantDatabaseAnalyticsService.php](file:///e:/Mern%20Stact%20Dev/multi-tenant-mern/multi-tenant-laravel/app/Services/TenantDatabaseAnalyticsService.php) can now detect manual database changes ('Drift') comparing actual schema against module expectations.

---

## Setup & Maintenance Commands

```bash
php artisan migrate                                    # Run new migrations
php artisan db:seed --class=DatabasePlanSeeder          # Seed the 3 plans
php artisan tenant:collect-db-stats                     # Manual stats collection
```

The scheduler runs `tenant:collect-db-stats` every hour automatically.

---

## Security Model

Each tenant's MySQL user is created with **minimal privileges**:
```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
  ON `tenant_xyz`.* TO 'tu_xyz'@'%';
-- No SUPER, FILE, PROCESS, GRANT OPTION
```

Tenants **never receive** these credentials. All access goes through the Laravel API.
