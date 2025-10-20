<?php

namespace App\Models\Customers;

use App\Models\Setting;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Employees\Employee;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'customer_type_id',
        'customer_group_id',
        'customer_province_id',
        'customer_zone_id',
        // 'opening_balance',
        'total_old_sales',
        'current_balance',
        'address',
        'city',
        'telephone',
        'mobile',
        'url',
        'email',
        'contact_name',
        'gps_coordinates',
        'mof_tax_number',
        'salesperson_id',
        'customer_payment_term_id',
        'discount_percentage',
        'credit_limit',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        // 'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'discount_percentage' => 'decimal:2',
        'credit_limit' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'name',
        'address',
        'city',
        'telephone',
        'mobile',
        'email',
        'contact_name',
        'mof_tax_number',
        'notes',
    ];

    protected $sortable = [
        'id',
        'code',
        'name',
        'customer_type_id',
        'customer_group_id',
        'customer_province_id',
        'customer_zone_id',
        'city',
        // 'opening_balance',
        // 'current_balance', // Removed: This is a computed accessor, cannot be sorted at DB level
        'salesperson_id',
        'discount_percentage',
        'credit_limit',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Customer::class, 'parent_id');
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function customerProvince(): BelongsTo
    {
        return $this->belongsTo(CustomerProvince::class);
    }

    public function customerZone(): BelongsTo
    {
        return $this->belongsTo(CustomerZone::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'salesperson_id', 'id');
    }

    public function customerPaymentTerm(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentTerm::class);
    }

    public function monthlyBalances(): HasMany
    {
        return $this->hasMany(CustomerBalanceMonthly::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('customer_type_id', $typeId);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('customer_group_id', $groupId);
    }

    public function scopeByProvince($query, $provinceId)
    {
        return $query->where('customer_province_id', $provinceId);
    }

    public function scopeByZone($query, $zoneId)
    {
        return $query->where('customer_zone_id', $zoneId);
    }

    public function scopeBySalesperson($query, $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('current_balance', '!=', 0);
    }

    public function scopeOverCreditLimit($query)
    {
        return $query->whereColumn('current_balance', '>', 'credit_limit')
                    ->whereNotNull('credit_limit');
    }

    /**
     * Custom sorting for current_balance field based on accessor calculation
     * This method is called by the Sortable trait when sorting by 'current_balance'
     */
    public function sortByCurrentBalance($query, $direction = 'asc')
    {
        // Subquery to get the latest closing balance
        $closingBalanceSubquery = \DB::table('customer_balance_monthlies')
            ->select('closing_balance')
            ->whereColumn('customer_balance_monthlies.customer_id', 'customers.id')
            ->where('transaction_total', '>', 0)
            ->where('closing_balance', '>', 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(1);

        // Subquery to get current month transaction
        $currentMonthSubquery = \DB::table('customer_balance_monthlies')
            ->select('transaction_total')
            ->whereColumn('customer_balance_monthlies.customer_id', 'customers.id')
            ->where('closing_balance', 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->limit(1);

        // Add calculated balance column and sort by it
        return $query->selectRaw(
            'customers.*,
            (COALESCE((' . $closingBalanceSubquery->toSql() . '), 0) +
             COALESCE((' . $currentMonthSubquery->toSql() . '), 0)) as calculated_current_balance'
        )
        ->mergeBindings($closingBalanceSubquery)
        ->mergeBindings($currentMonthSubquery)
        ->orderBy('calculated_current_balance', $direction);
    }

    // Helper Methods
    public function hasParent(): bool
    {
        return !is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function isOverCreditLimit(): bool
    {
        if (!$this->credit_limit) {
            return false;
        }
        return $this->current_balance > $this->credit_limit;
    }

    public function getBalanceStatusAttribute(): string
    {
        if ($this->current_balance > 0) {
            return 'credit';
        } elseif ($this->current_balance < 0) {
            return 'debit';
        }
        return 'balanced';
    }

    public function getFormattedGpsAttribute(): ?array
    {
        if (!$this->gps_coordinates) {
            return null;
        }

        $coordinates = explode(',', $this->gps_coordinates);
        if (count($coordinates) !== 2) {
            return null;
        }

        return [
            'latitude' => trim($coordinates[0]),
            'longitude' => trim($coordinates[1])
        ];
    }

    public function lastMonthRunningBalance(): float
    {
        $latestBalance = $this->monthlyBalances()
            ->where('closing_balance', '>', 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        return $latestBalance ? (float) $latestBalance->closing_balance : 0.0;
    }

    public function currentMonthTransaction(): float
    {
        $latestBalance = $this->monthlyBalances()
            ->where('closing_balance' , 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first(); 

        return $latestBalance ? (float) $latestBalance->transaction_total : 0.0;
    }

    public function getCurrentBalanceAttribute(): float
    {
        $customerId = $this->id;
        $closingBalanceData = CustomerBalanceMonthly::select('closing_balance')
            ->where('transaction_total', '>', 0)
            ->where('customer_id', $customerId)
            ->where('closing_balance' , '>', 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        $closingBalance = $closingBalanceData ? (float) $closingBalanceData->closing_balance : 0.0;

        $latestBalance = CustomerBalanceMonthly::select('transaction_total')
            ->where('customer_id', $customerId)
            ->where('closing_balance' , 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $thisMonthBalance =  $latestBalance ? (float) $latestBalance->transaction_total : 0.0;
         
        return $closingBalance + $thisMonthBalance;    
    }


    // Code Generation Methods
    
    /**
     * Generate the next customer code from settings
     */
    public static function generateNextCustomerCode(): string
    {
        $defaultValue = config('app.customer_code_start', 50000000);
        $nextNumber = Setting::getOrCreateCounter('customers', 'code_counter', $defaultValue);
        return (string) $nextNumber;
    }

    /**
     * Reserve the next code number (increment counter)
     */
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.customer_code_start', 50000000);
        // Atomically get and increment the counter with auto-creation
        $newValue = Setting::incrementValue('customers', 'code_counter', 1, $defaultValue);
        return (string) ($newValue - 1); // Return the value before increment
    }

    /**
     * Check if a code is unique
     */
    public static function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = self::withTrashed()->where('code', $code);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Get the next suggested code for frontend display
     */
    public static function getNextSuggestedCode(): string
    {
        return self::generateNextCustomerCode();
    }

    /**
     * Validate and set code for new customer
     */
    public function setCustomerCode(?string $userCode = null): string
    {
        if ($userCode) {
            // User provided custom code - validate uniqueness
            if (!self::isCodeUnique($userCode)) {
                throw new \InvalidArgumentException("Code '{$userCode}' is already in use.");
            }
            $this->code = $userCode;
            
            // Only increment counter if user used the suggested code
            $suggestedCode = self::generateNextCustomerCode();
            if ($userCode === $suggestedCode) {
                $defaultValue = config('app.customer_code_start', 50000000);
                Setting::incrementValue('customers', 'code_counter', 1, $defaultValue);
            }
        } else {
            // Auto-generate code
            $this->code = self::reserveNextCode();
        }
        
        return $this->code;
    }

    /**
     * Get allowed document file extensions for this model
     */
    public function getAllowedDocumentExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'txt'];
    }

    /**
     * Get maximum file size for document uploads (in bytes)
     */
    public function getMaxDocumentFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB for customer documents
    }

    /**
     * Get maximum number of documents allowed per customer
     */
    public function getMaxDocumentsCount(): int
    {
        return 15; // 15 documents maximum per customer
    }

    /**
     * Handle code setting on model creation
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($customer) {
            // Auto-set code if not provided
            if (!$customer->code) {
                $customer->setCustomerCode();
            }
        });
    }
}
