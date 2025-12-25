<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;

class TenantFeature extends Model
{
    protected $fillable = [
        'tenant_id',
        'feature_id',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the feature
     */
    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }
}
