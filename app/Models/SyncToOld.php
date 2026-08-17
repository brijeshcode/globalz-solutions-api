<?php

namespace App\Models;

use App\Traits\Authorable;
use Illuminate\Database\Eloquent\Model;

/**
 * Central "copied to legacy system" flag for the syncin feature.
 * One row per transaction record, keyed by the record's table name + id.
 */
class SyncToOld extends Model
{
    use Authorable;

    protected $table = 'sync_to_old';

    protected $fillable = [
        'model',
        'model_id',
        'is_synced',
    ];

    protected $casts = [
        'model_id'  => 'integer',
        'is_synced' => 'boolean',
    ];
}
