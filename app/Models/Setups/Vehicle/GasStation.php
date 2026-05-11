<?php

namespace App\Models\Setups\Vehicle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Setups\Vehicle\GasStationFactory;

class GasStation extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = ['name', 'balance', 'address', 'note', 'is_active'];

    protected $searchable = ['name', 'address', 'note'];

    protected $casts = [
        'balance'   => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): GasStationFactory
    {
        return GasStationFactory::new();
    }

    public function refills()
    {
        return $this->hasMany(CarRefill::class, 'gas_station_id');
    }

    public function payments()
    {
        return $this->hasMany(GasStationPayment::class, 'gas_station_id');
    }
}
