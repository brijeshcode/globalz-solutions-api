<?php

namespace App\Traits;


trait HasBooleanFilters
{
    protected function applyBooleanFilter($query, string $column, $value): void
    {
        $booleanValue = $this->parseBoolean($value);
        
        if ($booleanValue !== null) {
            $query->where($column, $booleanValue);
        }
    }
    
    protected function parseBoolean($value): ?bool
    {
        // Handle null/empty
        if ($value === null || $value === '') {
            return null;
        }
        
        // Handle actual booleans
        if (is_bool($value)) {
            return $value;
        }
        
        // Handle integers
        if (is_int($value)) {
            return $value === 1;
        }
        
        // Handle numeric strings
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        
        // Handle string representations
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            
            return match ($normalized) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => null
            };
        }
        
        return null;
    }
}
