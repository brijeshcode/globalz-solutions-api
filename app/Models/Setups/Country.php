<?php

namespace App\Models\Setups;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;

class Country extends Model
{
    /** @use HasFactory<\Database\Factories\Setups\CountryFactory> */
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'code',
        'iso2',
        'phone_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    protected $searchable = [
        'name',
        'code',
        'iso2',
        'phone_code',
    ];

    protected $sortable = [
        'id',
        'name',
        'is_active',
        'created_at',
        'updated_at',
    ];

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

    // Format phone number with country code
    public function formatPhoneNumber(?string $phoneNumber = null)
    {
        if (!$phoneNumber || !$this->phone_code) {
            return $phoneNumber;
        }

        // Remove any existing country code or formatting
        $cleanNumber = preg_replace('/[^\d]/', '', $phoneNumber);
        
        return $this->phone_code . ' ' . $phoneNumber;
    }

    // Get display name with code
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->iso2 . ')';
    }

    // Get full display with phone code
    public function getFullDisplayAttribute()
    {
        $display = $this->name . ' (' . $this->iso2 . ')';
        if ($this->phone_code) {
            $display .= ' ' . $this->phone_code;
        }
        return $display;
    }
}