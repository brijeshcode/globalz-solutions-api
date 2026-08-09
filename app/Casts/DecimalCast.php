<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DecimalCast implements Castable
{
    public function __construct(private ?int $precision = null)
    {
    }

    public static function castUsing(array $arguments)
    {
        return new static($arguments[0] ?? null);
    }

    public function get($model, $key, $value, $attributes): ?float
    {
        return $value !== null ? $this->round((float) $value) : null;
    }

    public function set($model, $key, $value, $attributes): ?float
    {
        return $value !== null ? $this->round((float) $value) : null;
    }

    private function round(float $value): float
    {
        return $this->precision !== null ? round($value, $this->precision) : $value;
    }
}



