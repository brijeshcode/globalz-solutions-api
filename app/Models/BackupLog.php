<?php

namespace App\Models;

use App\Traits\Authorable;
use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    use Authorable;

    /**
     * backup_logs lives in the TENANT DB (not landlord) so a tenant's database
     * dump + files form a self-contained, portable backup. Resolves to the tenant
     * connection because it is the default connection when a tenant is current.
     */
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
