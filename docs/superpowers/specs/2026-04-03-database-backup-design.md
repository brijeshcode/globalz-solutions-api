# Database Backup System — Design Spec

**Date:** 2026-04-03
**Status:** Approved

---

## Overview

A tenant-aware database backup system built as a service inside the existing Laravel application. Backs up each tenant's database daily via a scheduler, stores backups on a private local disk (always) plus optional remote destinations (S3, FTP, Dropbox) configured per-tenant in the settings table. Backup logs are stored in the landlord DB for central monitoring. GFS (Grandfather-Father-Son) retention policy automatically manages backup lifecycle.

**Out of scope (for now):** Landlord database backup.

---

## Access Control

All backup endpoints and features are restricted to `canSuperAdmin()` — developer and super admin roles only.

---

## Section 1: File Structure & Storage

### Storage Disk

A dedicated private `backup` disk defined in `config/filesystems.php`, pointing to `storage/app/backups`. Never under `public/` — no web-accessible URL. Files are downloaded via a controller that streams them directly.

### File Path Pattern

Mirrors the existing document storage pattern:

```
storage/app/backups/database/{tenant_key}/{year}/{month}/{tenant_key}-YYYY-MM-DD-HH-mm-ss.sql.gz
```

Example:
```
storage/app/backups/database/xyz/2026/04/xyz-2026-04-03-02-00-00.sql.gz
```

### App Structure

```
app/
├── Services/Backup/
│   ├── BackupService.php              — creates dump, compresses, stores to disk(s)
│   ├── BackupRetentionService.php     — GFS cleanup logic
│   └── BackupStorageService.php       — handles local + S3/FTP/Dropbox storage
├── Models/
│   └── BackupLog.php
├── Jobs/
│   ├── BackupTenantJob.php            — queued per-tenant backup
│   └── BackupRetentionCleanupJob.php  — nightly GFS cleanup
├── Console/Commands/
│   └── BackupAllTenantsCommand.php    — loops tenants, dispatches BackupTenantJob per tenant
└── Http/Controllers/Api/Backup/
    └── BackupController.php
```

---

## Section 2: `backup_logs` Table (Landlord DB)

Lives in the **landlord database** — not tenant DBs. This ensures backup records survive even if a tenant DB is corrupted, and allows super admin to monitor all tenants from one place.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | PK |
| `tenant_id` | bigint unsigned | FK to tenants |
| `tenant_key` | string | stored for reference even if tenant is deleted |
| `database_name` | string | which DB was backed up |
| `file_name` | string | e.g. `xyz-2026-04-03-02-00-00.sql.gz` |
| `file_path` | string | relative path on disk |
| `file_size` | bigint unsigned | in bytes |
| `disk` | enum | `local`, `s3`, `ftp`, `dropbox` |
| `status` | enum | `pending`, `running`, `success`, `failed` |
| `tier` | enum | `daily`, `weekly`, `monthly`, `yearly` (GFS tier) |
| `compression` | string | `gzip` (default, extensible) |
| `duration_seconds` | unsignedInteger | how long the dump took |
| `triggered_by` | bigint unsigned nullable | null = scheduler, user_id = manual trigger |
| `error_message` | text nullable | filled on failure |
| `expires_at` | timestamp nullable | set by retention service when scheduled for deletion |
| `created_by` | bigint unsigned nullable | FK to users |
| `updated_by` | bigint unsigned nullable | FK to users |
| `created_at` / `updated_at` | timestamps | |

### GFS Retention Policy

| Tier | Kept For | Promotion Rule |
|------|----------|----------------|
| `daily` | 30 days | After 30 days: keep first of each week → promote to `weekly`, delete rest |
| `weekly` | 26 weeks (~6 months) | After 26 weeks: keep first of each month → promote to `monthly`, delete rest |
| `monthly` | 120 months (10 years) | After 120 months: keep first of each year → promote to `yearly`, delete rest |
| `yearly` | Forever | Never deleted |

---

## Section 3: API Endpoints

All routes under `/api/backups`, protected by `canSuperAdmin()` middleware.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/backups` | List backups — paginated, filterable by tenant/status/tier/date |
| `POST` | `/api/backups/trigger` | Manually trigger backup for a tenant |
| `GET` | `/api/backups/{id}/download` | Stream download the `.sql.gz` file |
| `DELETE` | `/api/backups/{id}` | Delete backup (physical file + log record) |
| `GET` | `/api/backups/settings` | Get tenant's backup storage config |
| `PUT` | `/api/backups/settings` | Save tenant's backup storage config |

### Backup Settings (stored in `settings` table, group: `backup`)

| key | data_type | notes |
|-----|-----------|-------|
| `storage_drivers` | json | e.g. `["local","s3"]` — `local` always included, cannot be removed |
| `s3_key` | string | encrypted |
| `s3_secret` | string | encrypted |
| `s3_bucket` | string | |
| `s3_region` | string | |
| `ftp_host` | string | |
| `ftp_user` | string | |
| `ftp_password` | string | encrypted |
| `ftp_port` | number | default 21 |
| `dropbox_token` | string | encrypted |

---

## Section 4: Scheduler & Job Flow

### Daily Schedule (`routes/console.php`)

```php
Schedule::command('backup:all-tenants')->dailyAt('02:00');
Schedule::job(new BackupRetentionCleanupJob)->dailyAt('03:00');
```

### BackupAllTenantsCommand Flow

```
BackupAllTenantsCommand (runs at 02:00)
  └── loops all active tenants
      └── dispatches BackupTenantJob(tenant) for each tenant
```

### BackupTenantJob Flow

```
1. Create backup_log record  (status: pending)
2. Update status → running
3. Switch to tenant DB connection
4. Run mysqldump via Symfony Process → .sql file (temp)
5. Compress with gzip → .sql.gz
6. Store to local backup disk (always)
7. If S3 configured in settings  → upload to S3, log with disk: s3
8. If FTP configured in settings → upload via FTP, log with disk: ftp
9. If Dropbox configured         → upload to Dropbox, log with disk: dropbox
10. Update backup_log: status: success, file_size, duration_seconds, file_path, disk
11. Delete temp .sql file
On failure at any step:
  → Update backup_log: status: failed, error_message
```

### BackupRetentionCleanupJob Flow (runs at 03:00)

```
For each tenant:
  - daily backups > 30 days:
      keep first backup of each week → tier = weekly
      delete all other daily backups in that week (file + record)
  - weekly backups > 26 weeks:
      keep first backup of each month → tier = monthly
      delete all other weekly backups in that month (file + record)
  - monthly backups > 120 months:
      keep first backup of each year → tier = yearly
      delete all other monthly backups in that year (file + record)
  - yearly backups → never deleted
```

---

## Implementation Notes

- `mysqldump` is invoked via Symfony Process (already available in Laravel — no new dependencies)
- Backup credentials (S3/FTP/Dropbox) stored encrypted in `settings` table using existing `is_encrypted` flag
- Multiple storage destinations per tenant: one `backup_log` record is created per disk per backup run
- The `local` driver in `storage_drivers` setting cannot be removed — enforced at the service layer
- Download endpoint uses `response()->download()` streaming — file path is never exposed to the client
- `BackupLog` model lives in the landlord context and is never scoped to tenant DB — set `protected $connection = 'landlord'` on the model
- `mysqldump` DB credentials are read from the tenant's database config (host, port, database, username, password) as stored in the tenants table / Spatie multitenancy connection config — not from `.env`

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_backup_logs_table.php` | landlord migration |
| `app/Models/BackupLog.php` | Eloquent model |
| `app/Services/Backup/BackupService.php` | core dump + compress + store |
| `app/Services/Backup/BackupRetentionService.php` | GFS cleanup logic |
| `app/Services/Backup/BackupStorageService.php` | multi-disk storage handler |
| `app/Jobs/BackupTenantJob.php` | queued per-tenant backup job |
| `app/Jobs/BackupRetentionCleanupJob.php` | nightly GFS cleanup job |
| `app/Console/Commands/BackupAllTenantsCommand.php` | artisan command |
| `app/Http/Controllers/Api/Backup/BackupController.php` | API controller |
| `config/filesystems.php` | add `backup` disk |
| `routes/console.php` | scheduler registration |
| `routes/api.php` | route registration |
