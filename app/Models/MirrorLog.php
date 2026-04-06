<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MirrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'triggered_by',
        'started_at',
        'completed_at',
        'duration_seconds',
        'remote_host',
        'error_message',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('started_at');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
