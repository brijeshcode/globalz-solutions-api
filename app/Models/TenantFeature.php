<?php

namespace App\Models;

use Illuminate\Support\Facades\Config;

class TenantFeature
{
    /**
     * Return all feature flags as key => bool map, read from config.
     */
    public static function getForCurrentTenant(): array
    {
        return array_filter(Config::get('features', []), fn($v) => $v === true);
    }

    /**
     * Check if a single feature is enabled.
     */
    public static function isEnabled(string $featureKey): bool
    {
        return Config::get("features.{$featureKey}", false);
    }
}
