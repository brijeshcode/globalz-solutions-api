<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Sortable
{
    /**
     * Apply sorting to the query
     */
    public function scopeSortable(Builder $query, Request $request): Builder
    {
        $sortBy = $request->input('sort_by', $this->getDefaultSortField());
        $sortDirection = $request->input('sort_direction', $this->getDefaultSortDirection());

        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc'])
            ? strtolower($sortDirection)
            : $this->getDefaultSortDirection();

        // Validate sort field
        if (!$this->isSortableField($sortBy)) {
            $sortBy = $this->getDefaultSortField();
        }

        // Check if model has custom sorting method for this field
        $customSortMethod = 'sortBy' . str_replace('_', '', ucwords($sortBy, '_'));
        if (method_exists($this, $customSortMethod)) {
            return $this->$customSortMethod($query, $sortDirection);
        }

        return $query->orderBy($sortBy, $sortDirection);
    }

    /**
     * Check if field is sortable
     */
    protected function isSortableField(string $field): bool
    {
        $sortableFields = $this->getSortableFields();
        
        // If no sortable fields defined, allow all fillable fields
        if (empty($sortableFields)) {
            return in_array($field, $this->getFillable());
        }

        return in_array($field, $sortableFields);
    }

    /**
     * Get sortable fields - override this in your model
     */
    protected function getSortableFields(): array
    {
        return property_exists($this, 'sortable') ? $this->sortable : [];
    }

    /**
     * Get default sort field - override this in your model
     */
    protected function getDefaultSortField(): string
    {
        return property_exists($this, 'defaultSortField') ? $this->defaultSortField : 'id';
    }

    /**
     * Get default sort direction - override this in your model
     */
    protected function getDefaultSortDirection(): string
    {
        return property_exists($this, 'defaultSortDirection') ? $this->defaultSortDirection : 'asc';
    }

    /**
     * Get available sort options for frontend
     */
    public static function getSortOptions(): array
    {
        $model = new static();
        $fields = $model->getSortableFields();
        
        if (empty($fields)) {
            $fields = $model->getFillable();
        }

        $options = [];
        foreach ($fields as $field) {
            $options[] = [
                'value' => $field,
                'label' => ucwords(str_replace('_', ' ', $field)),
                'directions' => [
                    ['value' => 'asc', 'label' => 'Ascending'],
                    ['value' => 'desc', 'label' => 'Descending']
                ]
            ];
        }

        return $options;
    }
}