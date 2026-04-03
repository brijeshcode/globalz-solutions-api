<?php

namespace App\Models;

use App\Traits\Authorable;
use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    use Authorable;

    /**
     * Use the landlord connection — backup_logs lives in the landlord DB,
     * not in tenant DBs. This ensures records survive even if a tenant DB
     * is corrupted, and lets super admin view all tenants from one table.
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'tenant_key',
        'database_name',
        'file_name',
        'file_path',
        'file_size',
        'disk',
        'status',
        'tier',
        'compression',
        'duration_seconds',
        'triggered_by',
        'error_message',
        'expires_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'file_size'        => 'integer',
        'duration_seconds' => 'integer',
        'expires_at'       => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    // Tier constants
    public const TIER_DAILY   = 'daily';
    public const TIER_WEEKLY  = 'weekly';
    public const TIER_MONTHLY = 'monthly';
    public const TIER_YEARLY  = 'yearly';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class)->on('mysql');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }
}
