<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Configure the CSP header. Set to null to disable.
    |
    */
    'csp' => env('SECURITY_CSP', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; connect-src 'self' wss:;"),

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | Enable or disable audit logging for critical actions.
    |
    */
    'audit_enabled' => env('SECURITY_AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tenant API Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute for authenticated tenant API routes.
    |
    */
    'tenant_api_rate_limit' => env('TENANT_API_RATE_LIMIT', 60),

];
