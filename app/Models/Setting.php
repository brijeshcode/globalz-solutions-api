<?php

namespace App\Models;

use App\Traits\Authorable;
use App\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    use HasFactory, Authorable, HasDocuments;

    protected $fillable = [
        'group_name',
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
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get setting value by group and key
     */
    public static function get(string $group, string $key, $default = null, $autoCreate = false, string $dataType = self::TYPE_STRING)
    {
        $cacheKey = self::CACHE_PREFIX . $group . ':' . $key;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $key, $default, $autoCreate, $dataType) {
            $setting = self::where('group_name', $group)
                          ->where('key_name', $key)
                          ->first();
            
            if (!$setting) {
                if ($autoCreate && $default !== null) {
                    // Auto-create the setting with the default value
                    $setting = self::create([
                        'group_name' => $group,
                        'key_name' => $key,
                        'value' => $default,
                        'data_type' => $dataType,
                        'description' => "Auto-created setting for {$group}.{$key}"
                    ]);
                    
                    return $setting->getCastValue();
                }
                
                return $default;
            }
            
            return $setting->getCastValue();
        });
    }

    /**
     * Set setting value
     */
    public static function set(string $group, string $key, $value, string $dataType = self::TYPE_STRING, ?string $description = null): self
    {
        $setting = self::updateOrCreate(
            ['group_name' => $group, 'key_name' => $key],
            [
                'value' => $value,
                'data_type' => $dataType,
                'description' => $description,
            ]
        );

        // Clear cache
        $cacheKey = self::CACHE_PREFIX . $group . ':' . $key;
        Cache::forget($cacheKey);

        return $setting;
    }

    /**
     * Increment numeric setting with auto-creation support
     */
    public static function incrementValue(string $group, string $key, int $amount = 1, $defaultValue = null): int
    {
        return DB::transaction(function () use ($group, $key, $amount, $defaultValue) {
            $setting = self::where('group_name', $group)
                          ->where('key_name', $key)
                          ->lockForUpdate()
                          ->first();

            if (!$setting) {
                // Auto-create setting if it doesn't exist and default value is provided
                if ($defaultValue !== null) {
                    $setting = self::create([
                        'group_name' => $group,
                        'key_name' => $key,
                        'value' => $defaultValue,
                        'data_type' => self::TYPE_NUMBER,
                        'description' => "Auto-created counter for {$group}.{$key}"
                    ]);
                    $currentValue = (int) $defaultValue;
                } else {
                    throw new \InvalidArgumentException("Setting {$group}.{$key} does not exist and no default value provided");
                }
            } else if ($setting->data_type !== self::TYPE_NUMBER) {
                throw new \InvalidArgumentException("Setting {$group}.{$key} must be of type 'number'");
            } else {
                $currentValue = (int) $setting->value;
            }

            $newValue = $currentValue + $amount;
            
            $setting->update(['value' => $newValue]);

            // Clear cache
            $cacheKey = self::CACHE_PREFIX . $group . ':' . $key;
            Cache::forget($cacheKey);

            return $newValue;
        });
    }

    /**
     * Get numeric setting with auto-creation support for counters
     */
    public static function getOrCreateCounter(string $group, string $key, $defaultValue = 0): int
    {
        $setting = self::where('group_name', $group)
                      ->where('key_name', $key)
                      ->first();

        if (!$setting) {
            $setting = self::create([
                'group_name' => $group,
                'key_name' => $key,
                'value' => $defaultValue,
                'data_type' => self::TYPE_NUMBER,
                'description' => "Auto-created counter for {$group}.{$key}"
            ]);
            
            // Clear cache
            $cacheKey = self::CACHE_PREFIX . $group . ':' . $key;
            Cache::forget($cacheKey);
        }

        return (int) $setting->getCastValue();
    }

    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = self::CACHE_PREFIX . 'group:' . $group;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            return self::where('group_name', $group)
                      ->get()
                      ->mapWithKeys(function ($setting) {
                          return [$setting->key_name => $setting->getCastValue()];
                      })
                      ->toArray();
        });
    }

    /**
     * Delete setting
     */
    public static function remove(string $group, string $key): bool
    {
        $setting = self::where('group_name', $group)
                      ->where('key_name', $key)
                      ->first();

        if ($setting) {
            $setting->delete();
            
            // Clear cache
            $cacheKey = self::CACHE_PREFIX . $group . ':' . $key;
            Cache::forget($cacheKey);
            
            return true;
        }

        return false;
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        Cache::flush(); // Simple approach, or use Cache::tags if available
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
     * Scopes
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group_name', $group);
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
     * Get the module folder name for file organization
     */
    protected function getModuleFolderName(): string
    {
        return $this->group_name ?? 'settings';
    }

    /**
     * Get allowed document file extensions for settings
     */
    public function getAllowedDocumentExtensions(): array
    {
        // For company logo/stamp, only allow image files
        if ($this->group_name === 'company' && in_array($this->key_name, ['logo', 'stamp'])) {
            return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp'];
        }

        // Default allowed extensions for other settings
        return ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'ico', 'png', 'gif'];
    }

    /**
     * Get maximum file size for document uploads
     */
    public function getMaxDocumentFileSize(): int
    {
        // For company logo/stamp, limit to 2MB
        if ($this->group_name === 'company' && in_array($this->key_name, ['logo', 'stamp'])) {
            return 2 * 1024 * 1024; // 2MB
        }

        // Default size for other settings
        return 5 * 1024 * 1024; // 5MB
    }

    /**
     * Boot method to clear cache on model events
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            $cacheKey = self::CACHE_PREFIX . $setting->group_name . ':' . $setting->key_name;
            Cache::forget($cacheKey);
            Cache::forget(self::CACHE_PREFIX . 'group:' . $setting->group_name);
        });

        static::deleted(function ($setting) {
            $cacheKey = self::CACHE_PREFIX . $setting->group_name . ':' . $setting->key_name;
            Cache::forget($cacheKey);
            Cache::forget(self::CACHE_PREFIX . 'group:' . $setting->group_name);
        });
    }
}
