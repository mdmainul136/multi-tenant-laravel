---
description: Maintenance workflow for module development and task tracking
---

# Module Maintenance Workflow

> Use this workflow whenever you are working on any module in the project.
> This ensures consistent thinking, planning, execution, and documentation.
> **CRITICAL**: You MUST update the module's `module_task.md` at EVERY step — just like a developer updating their task board.

---

## 🔴 MANDATORY RULE: Task Tracking

**Before writing even one line of code**, you MUST:

1. Open the target module's `module_task.md`.
2. Add a new task entry describing what you are about to do.
3. Mark it as `⏳ In Progress`.
4. After completing the task, mark it as `✅ Done` with a one-line summary.
5. If a task fails or needs changes, mark it as `❌ Failed` or `🔄 Revised` with reason.

**Format for new tasks in module_task.md:**
```markdown
## Phase N: [Phase Name] [IN PROGRESS]
| # | Task | Status | Notes |
| :--- | :--- | :--- | :--- |
| 1 | [What you're doing] | ⏳ | Started YYYY-MM-DD |
```

**After completion, update to:**
```markdown
| 1 | [What you did] | ✅ | Completed YYYY-MM-DD — [one-line summary] |
```

---

## Step 1: Read Module Context

Before touching any code, understand the module's current state:

1. Read the module's `DOCUMENTATION.md` — understand architecture, models, services.
2. Read the module's `module_task.md` — check what has been done and what's pending.
3. Check `module.json` for active features and dependencies.

// turbo

## Step 2: Update Task Log (BEFORE coding)

Open `module_task.md` and:
1. Add a new phase or task entry for the current work.
2. Mark it as `⏳ In Progress`.
3. Be specific: which files will be created, modified, or deleted.

Example:
```markdown
## Phase 3: Add Barcode Scanning [IN PROGRESS]
| # | Task | Status | Notes |
| :--- | :--- | :--- | :--- |
| 1 | Create `BarcodeService.php` | ⏳ | Started 2026-02-22 |
| 2 | Add barcode route to `api.php` | [ ] | — |
| 3 | Update POS controller | [ ] | — |
```

## Step 3: Read Migrations

Always cross-reference with migration schemas before modifying models:

```bash
dir database\migrations\tenant\modules\<module_name>\
```

// turbo

## Step 4: Implement

Follow these rules during implementation:

1. All tenant models MUST extend `TenantBaseModel`.
2. Use `HasFactory` trait where applicable.
3. Define `$fillable`, `$casts`, and relationships based on migration schemas.
4. Follow the standardized folder structure:
   - `Actions/` — Single-responsibility business logic
   - `Controllers/` — HTTP request handling  
   - `Services/` — Complex business logic and integrations
   - `DTOs/` — Data transfer objects
   - `Jobs/` — Background processing
   - `routes/` — API definitions

## Step 5: Update Task Log (DURING coding)

After each sub-task completes:
1. Go back to `module_task.md`.
2. Mark the completed sub-task as `✅`.
3. Mark the next sub-task as `⏳`.

Example (after completing task 1):
```markdown
| 1 | Create `BarcodeService.php` | ✅ | Done — Added scan(), validate(), generate() methods |
| 2 | Add barcode route to `api.php` | ⏳ | Starting now |
| 3 | Update POS controller | [ ] | — |
```

// turbo

## Step 6: Verify

Run syntax checks on all modified files:

```bash
php -l <file_path>
```

Validate that model relationships match foreign keys in migrations.

## Step 7: Update Documentation

After implementation, update BOTH files:

1. **`DOCUMENTATION.md`**: Add new models, services, or endpoints to the reference tables.
2. **`module_task.md`**: Mark ALL tasks as `✅ Done`, mark the phase as `[COMPLETE]`.

Example (final state):
```markdown
## Phase 3: Add Barcode Scanning [COMPLETE]
| # | Task | Status | Notes |
| :--- | :--- | :--- | :--- |
| 1 | Create `BarcodeService.php` | ✅ | Done — Added scan(), validate(), generate() methods |
| 2 | Add barcode route to `api.php` | ✅ | Done — POST /pos/barcode/scan |
| 3 | Update POS controller | ✅ | Done — Added scanBarcode() method |
```

## Step 8: Self-Correct

If errors occur:
1. Analyze the error message carefully.
2. Fix the issue and retry.
3. Log the fix in `module_task.md`:
```markdown
| 4 | Fix: namespace issue in BarcodeService | ✅ | Hotfix — wrong import path |
```

---

> [!IMPORTANT]
> **The `module_task.md` is the source of truth for what happened in each module.**
> Every change, fix, and update MUST be logged there — no exceptions.
> Think of it as your commit history for that module.

// turbo-all
