<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AttachCacheVersion
{
    private const CACHE_KEY = 'app:cache_versions';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $versions = self::getVersions();
        $response->headers->set('X-Cache-Versions', json_encode($versions));

        return $response;
    }

    /**
     * Get cache versions from Laravel cache (hits DB only on cache miss)
     */
    public static function getVersions(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $value = \App\Models\Setting::where('group_name', 'app')
                ->where('key_name', 'cache_versions')
                ->first();
            if (!$value) {
                return ['global' => 1];
            }

            $decoded = json_decode($value->value, true);
            return is_array($decoded) ? $decoded : ['global' => 1];
        });
    }

    /**
     * Invalidate a specific cache key or global
     */
    public static function invalidate(string $key): array
    {
        $versions = self::getVersions();
        
        if ($key === 'global') {
            // Bump all versions
            foreach ($versions as $k => $v) {
                $versions[$k] = $v + 1;
            }
        } else {
            // Bump specific key (create if doesn't exist)
            $versions[$key] = ($versions[$key] ?? 0) + 1;
        }

        // Save to DB
        \App\Models\Setting::set('app', 'cache_versions', json_encode($versions), 'json', 'Cache version map for frontend cache invalidation');

        // Clear the Laravel cache so next request picks up new versions
        Cache::forget(self::CACHE_KEY);

        return $versions;
    }
}
