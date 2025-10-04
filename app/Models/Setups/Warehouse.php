<?php

namespace App\Models\Setups;

use App\Models\Employees\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;

class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\Setups\WarehouseFactory> */
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'note',
        'is_active',
        'is_default',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    protected $searchable = [
        'name','note', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Accessors
    public function getFullAddressAttribute()
    {
        $address = $this->address_line_1;
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        $address .= ', ' . $this->country;
        
        return $address;
    }

    // Helper methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function activate()
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        return $this->update(['is_active' => false]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_warehouses')
                    ->withTimestamps()
                    ->withPivot('is_primary');
    }
}