<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key_name',
        'value',
        'data_type',
        'description',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    // Data type constants
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_JSON = 'json';

    public static function getDataTypes(): array
    {
        return [
            self::TYPE_STRING,
            self::TYPE_NUMBER,
            self::TYPE_BOOLEAN,
            self::TYPE_JSON,
        ];
    }

    // Cache key prefix
    private const CACHE_PREFIX = 'user_setting:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get user setting value by key
     */
    public static function get(int $userId, string $key, $default = null)
    {
        $cacheKey = self::CACHE_PREFIX . $userId . ':' . $key;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $key, $default) {
            $setting = self::where('user_id', $userId)
                          ->where('key_name', $key)
                          ->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $setting->getCastValue();
        });
    }

    /**
     * Set user setting value
     */
    public static function set(int $userId, string $key, $value, string $dataType = self::TYPE_STRING, ?string $description = null): self
    {
        $setting = self::updateOrCreate(
            ['user_id' => $userId, 'key_name' => $key],
            [
                'value' => $value,
                'data_type' => $dataType,
                'description' => $description,
            ]
        );

        // Clear cache
        $cacheKey = self::CACHE_PREFIX . $userId . ':' . $key;
        Cache::forget($cacheKey);

        return $setting;
    }

    /**
     * Get all settings for a user
     */
    public static function getAllForUser(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . 'user:' . $userId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return self::where('user_id', $userId)
                      ->get()
                      ->mapWithKeys(function ($setting) {
                          return [$setting->key_name => $setting->getCastValue()];
                      })
                      ->toArray();
        });
    }

    /**
     * Delete user setting
     */
    public static function remove(int $userId, string $key): bool
    {
        $setting = self::where('user_id', $userId)
                      ->where('key_name', $key)
                      ->first();

        if ($setting) {
            $setting->delete();
            
            // Clear cache
            $cacheKey = self::CACHE_PREFIX . $userId . ':' . $key;
            Cache::forget($cacheKey);
            
            return true;
        }

        return false;
    }

    /**
     * Set multiple settings for a user
     */
    public static function setMultiple(int $userId, array $settings): void
    {
        foreach ($settings as $key => $config) {
            $value = $config['value'] ?? $config;
            $dataType = $config['data_type'] ?? self::TYPE_STRING;
            $description = $config['description'] ?? null;
            
            self::set($userId, $key, $value, $dataType, $description);
        }
    }

    /**
     * Get value with proper casting
     */
    public function getCastValue()
    {
        $value = $this->is_encrypted ? Crypt::decrypt($this->value) : $this->value;

        return match ($this->data_type) {
            self::TYPE_NUMBER => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : 0,
            self::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    /**
     * Set value with encryption if needed
     */
    public function setValueAttribute($value): void
    {
        $processedValue = match ($this->data_type) {
            self::TYPE_JSON => is_array($value) ? json_encode($value) : $value,
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            default => (string) $value,
        };

        $this->attributes['value'] = $this->is_encrypted ? Crypt::encrypt($processedValue) : $processedValue;
    }

    /**
     * Relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key_name', $key);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('data_type', $type);
    }

    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    /**
     * Boot method to clear cache on model events
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            $cacheKey = self::CACHE_PREFIX . $setting->user_id . ':' . $setting->key_name;
            Cache::forget($cacheKey);
            Cache::forget(self::CACHE_PREFIX . 'user:' . $setting->user_id);
        });

        static::deleted(function ($setting) {
            $cacheKey = self::CACHE_PREFIX . $setting->user_id . ':' . $setting->key_name;
            Cache::forget($cacheKey);
            Cache::forget(self::CACHE_PREFIX . 'user:' . $setting->user_id);
        });
    }
}
