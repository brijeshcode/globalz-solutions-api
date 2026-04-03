# Database Backup System Implementation Plan

> **Status: IMPLEMENTED** — All tasks completed on 2026-04-03.

**Goal:** Build a tenant-aware database backup system with daily scheduled backups, GFS retention, private local storage, optional remote destinations, and a REST API for listing, downloading, triggering, and deleting backups.

**Architecture:** Custom service layer inside the app under `app/Services/Backup/`. A daily artisan command loops all active tenants and runs backups **synchronously** (no queue worker — shared hosting). Uses `ifsnop/mysqldump-php` (pure PHP) to dump the tenant DB directly to a gzipped file. A separate artisan command handles GFS rotation nightly. All `backup_logs` records live in the landlord DB. A dedicated `BackupController` exposes six endpoints.

**Tech Stack:** Laravel 12, PHP 8.2, `ifsnop/mysqldump-php` (pure PHP — no shell_exec needed), `spatie/laravel-multitenancy` for tenant context switching.

**Key decisions made during implementation:**
- `shell_exec()` and `proc_open()` are disabled on shared hosting → replaced Symfony Process with `ifsnop/mysqldump-php`
- Queue workers not available on shared hosting → all jobs run synchronously via artisan commands
- `ifsnop/mysqldump-php` supports built-in GZIP → dumps directly to `.sql.gz`, no separate compress step
- Scheduler confirmed working via `Schedule::call()` heartbeat test in `routes/console.php`
- `BackupRetentionCleanupJob` kept in codebase but not used — replaced by `BackupRetentionCleanupCommand` for cron

---

## File Map (as implemented)

| File | Status | Responsibility |
|------|--------|----------------|
| `config/filesystems.php` | ✅ Modified | Added `backup` private disk → `storage/app/backups` |
| `config/logging.php` | — | No change needed |
| `database/migrations/landlord/2026_04_03_000001_create_backup_logs_table.php` | ✅ Created & migrated | Landlord migration for backup_logs |
| `app/Models/BackupLog.php` | ✅ Created | Eloquent model on landlord `mysql` connection |
| `app/Services/Backup/BackupService.php` | ✅ Created | Pure PHP dump via ifsnop, gzip, store locally |
| `app/Services/Backup/BackupStorageService.php` | ✅ Created | Push to S3/FTP/Dropbox from tenant settings |
| `app/Services/Backup/BackupRetentionService.php` | ✅ Created | GFS cleanup logic |
| `app/Jobs/BackupTenantJob.php` | ✅ Created | Kept for future VPS/queue use — not used on shared hosting |
| `app/Jobs/BackupRetentionCleanupJob.php` | ✅ Created | Kept for future VPS/queue use — not used on shared hosting |
| `app/Console/Commands/BackupAllTenantsCommand.php` | ✅ Created | Loops tenants, calls BackupService directly (synchronous) |
| `app/Console/Commands/BackupRetentionCleanupCommand.php` | ✅ Created | Loops tenants, calls BackupRetentionService directly |
| `app/Http/Controllers/Api/Backup/BackupController.php` | ✅ Created | list, trigger (sync), download, delete, settings |
| `routes/api.php` | ✅ Modified | 6 backup routes registered |
| `routes/console.php` | ✅ Modified | backup:all-tenants @ 02:00, backup:retention-cleanup @ 03:00 |

---

## What Runs & How

### Scheduled (via existing cPanel cron `* * * * * php artisan schedule:run`)
```
02:00 daily → php artisan backup:all-tenants
    loops all active tenants
    for each tenant: BackupService::run() → ifsnop dump → .sql.gz → storage/app/backups
    if remote configured: BackupStorageService::pushToRemote()

03:00 daily → php artisan backup:retention-cleanup
    loops all active tenants
    BackupRetentionService::runForTenant() → GFS rotation
```

### Manual (via API)
```
POST /api/backups/trigger
    → BackupService::run() for current tenant (synchronous, returns result immediately)
    → BackupStorageService::pushToRemote() if configured
```

---

## GFS Retention Policy

| Tier | Kept For | Rule |
|------|----------|------|
| `daily` | 30 days | After 30d: keep first of each ISO week → promote to `weekly`, delete rest |
| `weekly` | 26 weeks | After 26w: keep first of each month → promote to `monthly`, delete rest |
| `monthly` | 120 months (10 years) | After 120m: keep first of each year → promote to `yearly`, delete rest |
| `yearly` | Forever | Never deleted |

---

## API Endpoints

All protected by `canSuperAdmin()` (developer + super admin).

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/backups` | List backup logs (filter: tenant_id, status, tier, disk, date) |
| POST | `/api/backups/trigger` | Manual backup for current tenant (synchronous) |
| GET | `/api/backups/settings` | Get tenant backup storage settings |
| PUT | `/api/backups/settings` | Save S3/FTP/Dropbox config |
| GET | `/api/backups/{id}/download` | Stream download `.sql.gz` |
| DELETE | `/api/backups/{id}` | Delete file + log record |

---

## Backup Settings (stored in `settings` table, group: `backup`)

| key | encrypted | notes |
|-----|-----------|-------|
| `storage_drivers` | no | JSON array e.g. `["local","s3"]` — `local` always enforced |
| `s3_key` | ✅ | |
| `s3_secret` | ✅ | |
| `s3_bucket` | no | |
| `s3_region` | no | |
| `ftp_host` | no | |
| `ftp_user` | no | |
| `ftp_password` | ✅ | |
| `ftp_port` | no | default 21 |
| `dropbox_token` | ✅ | |

---

## Notes for Future Work

- **Tests:** Skipped during initial implementation — to be added in a follow-up session
- **VPS migration:** If server moves to VPS, re-enable `BackupTenantJob` + `BackupRetentionCleanupJob` and switch `BackupAllTenantsCommand` back to dispatching jobs for parallel execution
- **Landlord DB backup:** Out of scope — add when needed
- **mysqldump binary:** `shell_exec()` disabled on shared host — `ifsnop/mysqldump-php` used instead (pure PHP over PDO)
