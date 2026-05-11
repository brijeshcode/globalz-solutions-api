<?php

namespace App\Models\Setups\Vehicle;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\CarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, HasBooleanFilters;

    protected $fillable = ['name', 'plate_number', 'year', 'color', 'make', 'model', 'note', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $searchable = ['name', 'plate_number', 'make', 'model', 'color', 'note'];

    protected $casts = [
        'is_active' => 'boolean',
        'year'      => 'integer',
    ];

    protected static function newFactory(): CarFactory
    {
        return CarFactory::new();
    }

    public function refills()
    {
        return $this->hasMany(CarRefill::class, 'car_id');
    }
}
