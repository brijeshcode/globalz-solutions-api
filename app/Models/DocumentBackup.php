<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentBackup extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'document_id',
        'disk',
        'status',
        'file_path',
        'file_size',
        'year',
        'month',
        'module',
        'attempts',
        'last_error',
        'backed_up_at',
    ];

    protected $casts = [
        'file_size'    => 'integer',
        'year'         => 'integer',
        'month'        => 'integer',
        'attempts'     => 'integer',
        'backed_up_at' => 'datetime',
    ];
}
