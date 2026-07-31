<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP delivery policy
    |--------------------------------------------------------------------------
    |
    | Endpoints are intentionally fixed in this file. Integration records may
    | contain identifiers and credentials, but never an arbitrary destination
    | URL. This prevents an administrator field from becoming an SSRF vector.
    |
    */
    'http' => [
        'timeout_seconds' => 8,
        'connect_timeout_seconds' => 3,
        'attempts' => 3,
        'retry_delay_milliseconds' => 250,
        'processing_stale_after_seconds' => 120,
        'max_delivery_attempts' => 8,
        'retry_batch_size' => 100,
        'max_error_length' => 2000,
    ],

    'utmify' => [
        'endpoint' => 'https://api.utmify.com.br/api-credentials/orders',
        'default_platform' => 'EmporioCacau',
    ],

    'meta' => [
        'base_url' => 'https://graph.facebook.com',
        'graph_version' => 'v25.0',
    ],

    'tiktok' => [
        'endpoint' => 'https://business-api.tiktok.com/open_api/v1.3/event/track/',
    ],
];
