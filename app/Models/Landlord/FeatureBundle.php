<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeatureBundle extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'key',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Features included in this bundle.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'bundle_features')
            ->withTimestamps();
    }

}
