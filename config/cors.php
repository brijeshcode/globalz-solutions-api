<?php

return [


    'paths' => explode(',', env('CORS_PATHS', 'api/*,sanctum/csrf-cookie')),

    'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', '*')),

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', '*')),

    'exposed_headers' => [],

    'max_age' => env('CORS_MAX_AGE', 0),

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),
];