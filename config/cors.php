<?php

// Only the frontend origins listed in FRONTEND_URLS may call the API from a browser.
return [
    'paths' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_URLS', 'http://localhost:3000'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'X-Cart-Token', 'Idempotency-Key', 'X-XSRF-TOKEN'],
    'exposed_headers' => ['X-Cart-Token'],
    'max_age' => 600,
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
