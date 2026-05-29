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
    'csp' => env('SECURITY_CSP'),

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
    | Se aplica POR USUARIO (ver RateLimiter 'tenant-api' en AppServiceProvider).
    | El polling del inbox/WhatsApp Directo cada 5s consume ~40-48 req/min por agente.
    |
    */
    'tenant_api_rate_limit' => env('TENANT_API_RATE_LIMIT', 120),

];
