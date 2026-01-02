<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | When set to true, activity logs will be processed asynchronously via
    | queue jobs. This is useful for high-traffic applications to prevent
    | blocking the main request.
    |
    | When set to false, activity logs will be processed synchronously
    | (directly during the request). This is faster and simpler for
    | low to medium traffic applications.
    |
    | Default: false (sync - direct processing)
    |
    */

    'async' => env('ACTIVITY_LOG_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Batch Time Window (seconds)
    |--------------------------------------------------------------------------
    |
    | Changes that occur within this time window will be grouped into the
    | same batch. This helps group related changes together (e.g., updating
    | a Sale and its items in the same request).
    |
    | Default: 2 seconds
    |
    */

    'batch_window' => env('ACTIVITY_LOG_BATCH_WINDOW', 2),

    /*
    |--------------------------------------------------------------------------
    | Auto-Delete Old Logs
    |--------------------------------------------------------------------------
    |
    | Automatically delete activity logs older than the specified number of days.
    | Set to null or 0 to disable auto-deletion and keep logs indefinitely.
    |
    | Recommended values:
    | - 30 days: For applications with high activity
    | - 90 days: For medium activity (default)
    | - 365 days: For compliance/audit requirements
    | - null: Never delete (keep forever)
    |
    | Default: 90 days
    |
    */

    'retention_days' => env('ACTIVITY_LOG_RETENTION_DAYS', 400),

    /*
    |--------------------------------------------------------------------------
    | Auto-Cleanup Schedule
    |--------------------------------------------------------------------------
    |
    | Enable automatic scheduled cleanup of old activity logs.
    | When enabled, old logs will be automatically deleted based on the
    | retention_days setting above.
    |
    | This requires Laravel scheduler to be configured (cron job).
    |
    | Default: false (manual cleanup only)
    |
    */

    'auto_cleanup' => env('ACTIVITY_LOG_AUTO_CLEANUP', true),

    /*
    |--------------------------------------------------------------------------
    | Model Field Mappings (Aliases)
    |--------------------------------------------------------------------------
    |
    | Define field aliases for models. This allows you to use consistent
    | field names across your frontend, even when different models use
    | different field names for the same concept.
    |
    | Format:
    | 'ModelClass' => [
    |     'standardFieldName' => 'actualFieldName',
    | ],
    |
    | Example:
    | Item model has 'description' but we want to call it 'name':
    | 'App\Models\Items\Item' => [
    |     'name' => 'description',  // Maps 'name' to 'description' field
    | ],
    |
    | Now when you request 'name' in model_relations, it will fetch
    | 'description' from the database but return it as 'name' to frontend.
    |
    */

    'model_field_mappings' => [
        // Item model - use description as name
        'App\Models\Items\Item' => [
            'name' => 'description',
        ],

        // Add more model field mappings as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Relations for Display
    |--------------------------------------------------------------------------
    |
    | Define which relations to load when displaying activity log details
    | for child models. This helps show related data (like product name
    | for SaleItems) in the activity log.
    |
    | Format:
    | 'ModelClass' => [
    |     'relationName' => ['field1', 'field2', ...],
    | ],
    |
    | Examples:
    | - Single relation: 'item' => ['id', 'name', 'code']
    | - Multiple relations: both 'item' and 'sale'
    | - No relations: Don't add the model to this array
    |
    | Note: Field names can use aliases defined in model_field_mappings above
    |
    */

    'model_relations' => [
        // SaleItems - load item (product) details
        'App\Models\Customers\SaleItems' => [
            'item' => ['id', 'name', 'code'],  // 'name' will map to 'description'
        ],

        // Add more model-relation mappings as needed
        'App\Models\Customers\CustomerReturnItem' => [
            'item' => ['id', 'name', 'code'],
        ],
        
        // Add more model-relation mappings as needed
        // 'App\Models\Customers\PurchaseItems' => [
        //     'item' => ['id', 'name', 'code'],
        // ],
    ],

];
