<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-Webchat-Token', 'X-Hub-Signature-256', 'Stripe-Signature', 'X-API-Token'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
