<?php

namespace App\Traits;

use App\Http\Middleware\AttachCacheVersion;

trait InvalidatesCacheVersion
{
    public static function getCacheVersionKey(): string
    {
        return static::$cacheVersionKey ?? 'global';
    }

    public static function bootInvalidatesCacheVersion(): void
    {
        static::created(fn () => AttachCacheVersion::invalidate(static::getCacheVersionKey()));
        static::updated(fn () => AttachCacheVersion::invalidate(static::getCacheVersionKey()));
        static::deleted(fn () => AttachCacheVersion::invalidate(static::getCacheVersionKey()));
    }
}
