<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Searchable
{
    /**
     * Apply search to the query
     */
    public function scopeSearchable(Builder $query, Request $request): Builder
    {
        $searchTerm = $request->input('search');
        $searchField = $request->input('search_field');

        if (empty($searchTerm)) {
            return $query;
        }

        // If specific field is provided and it's searchable
        if ($searchField && $this->isSearchableField($searchField)) {
            return $this->searchInField($query, $searchField, $searchTerm);
        }

        // Search across all searchable fields
        return $this->searchInAllFields($query, $searchTerm);
    }

    /**
     * Search in a specific field
     */
    protected function searchInField(Builder $query, string $field, string $term): Builder
    {
        return $query->where($field, 'LIKE', "%{$term}%");
    }

    /**
     * Search across all searchable fields
     */
    protected function searchInAllFields(Builder $query, string $term): Builder
    {
        $searchableFields = $this->getSearchableFields();

        return $query->where(function ($subQuery) use ($searchableFields, $term) {
            foreach ($searchableFields as $field) {
                $subQuery->orWhere($field, 'LIKE', "%{$term}%");
            }
        });
    }

    /**
     * Check if field is searchable
     */
    protected function isSearchableField(string $field): bool
    {
        return in_array($field, $this->getSearchableFields());
    }

    /**
     * Get searchable fields - override this in your model
     */
    protected function getSearchableFields(): array
    {
        if (property_exists($this, 'searchable')) {
            return $this->searchable;
        }

        // Default to common text fields if not specified
        $defaultFields = [];
        $commonFields = ['name', 'title', 'description', 'code', 'email'];
        
        foreach ($commonFields as $field) {
            if (in_array($field, $this->getFillable())) {
                $defaultFields[] = $field;
            }
        }

        return $defaultFields;
    }

    /**
     * Advanced search with multiple filters
     */
    public function scopeAdvancedSearch(Builder $query, Request $request): Builder
    {
        $filters = $request->input('filters', []);

        foreach ($filters as $field => $value) {
            if (empty($value) || !$this->isSearchableField($field)) {
                continue;
            }

            // Handle different filter types
            if (is_array($value)) {
                // Multiple values (e.g., checkboxes)
                $query->whereIn($field, $value);
            } elseif (str_contains($value, '|')) {
                // Range values (e.g., "100|500" for between 100 and 500)
                $range = explode('|', $value);
                if (count($range) === 2) {
                    $query->whereBetween($field, [$range[0], $range[1]]);
                }
            } else {
                // Single value
                $query->where($field, 'LIKE', "%{$value}%");
            }
        }

        return $query;
    }

    /**
     * Get search configuration for frontend
     */
    public static function getSearchConfig(): array
    {
        $model = new static();
        $fields = $model->getSearchableFields();

        $config = [
            'fields' => [],
            'global_search' => true,
            'advanced_search' => true
        ];

        foreach ($fields as $field) {
            $config['fields'][] = [
                'name' => $field,
                'label' => ucwords(str_replace('_', ' ', $field)),
                'type' => $model->getFieldType($field),
                'placeholder' => "Search by " . str_replace('_', ' ', $field)
            ];
        }

        return $config;
    }

    /**
     * Get field type for search input
     */
    protected function getFieldType(string $field): string
    {
        // You can customize this based on your field types
        $numericFields = ['id', 'price', 'cost', 'amount', 'quantity', 'balance'];
        $emailFields = ['email'];
        $dateFields = ['created_at', 'updated_at', 'date', 'start_date', 'end_date'];

        if (in_array($field, $numericFields) || str_contains($field, 'price') || str_contains($field, 'cost')) {
            return 'number';
        }

        if (in_array($field, $emailFields)) {
            return 'email';
        }

        if (in_array($field, $dateFields) || str_contains($field, 'date')) {
            return 'date';
        }

        return 'text';
    }
}