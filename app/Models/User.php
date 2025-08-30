<?php

namespace App\Models;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected $searchable = [
        'name',
        'email',
    ];

    protected $sortable = [
        'id',
        'name',
        'email',
        'role',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'name';
    protected $defaultSortDirection = 'asc';

    /**
     * Role constants for better code maintainability
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SALESMAN = 'salesman';
    public const ROLE_WAREHOUSE_MANAGER = 'warehouse_manager';
    public const ROLE_DEVELOPER = 'developer';

    /**
     * Get all available roles
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALESMAN,
            self::ROLE_WAREHOUSE_MANAGER,
            self::ROLE_DEVELOPER,
        ];
    }

    /**
     * Get roles visible to users (excluding developer)
     */
    public static function getVisibleRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_SALESMAN,
            self::ROLE_WAREHOUSE_MANAGER,
        ];
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Check if user is admin (including super admin)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Check if user is salesman
     */
    public function isSalesman(): bool
    {
        return $this->hasRole(self::ROLE_SALESMAN);
    }

    /**
     * Check if user is warehouse manager
     */
    public function isWarehouseManager(): bool
    {
        return $this->hasRole(self::ROLE_WAREHOUSE_MANAGER);
    }

    /**
     * Check if user is developer
     */
    public function isDeveloper(): bool
    {
        return $this->hasRole(self::ROLE_DEVELOPER);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope to get only active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get users by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get the employee associated with this user
     */
    public function employee()
    {
        return $this->hasOne(\App\Models\Employees\Employee::class);
    }
}