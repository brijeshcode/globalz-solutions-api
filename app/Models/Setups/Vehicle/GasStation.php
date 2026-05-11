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

    protected static function newFactory(): GasStationFactory
    {
        return GasStationFactory::new();
    }
}
