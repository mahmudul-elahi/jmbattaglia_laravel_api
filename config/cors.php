<?php

return [
    'paths' => ['api/*'], 'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_URL', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'Idempotency-Key', 'X-Lookup-Token', 'X-Request-Id'],
    'exposed_headers' => ['X-Request-Id'], 'max_age' => 600, 'supports_credentials' => false,
];
