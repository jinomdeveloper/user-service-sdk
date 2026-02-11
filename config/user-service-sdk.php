<?php

// config for Jinom/UserServiceSdk
return [

    /*
    |--------------------------------------------------------------------------
    | Keycloak Configuration (untuk token refresh)
    |--------------------------------------------------------------------------
    */
    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realm' => env('KEYCLOAK_REALM'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Service Configuration
    |--------------------------------------------------------------------------
    */
    'user_service' => [
        'base_url' => env('USER_SERVICE_URL'),
        'timeout' => env('USER_SERVICE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'enabled' => env('USER_SERVICE_SYNC_ENABLED', true),
        'queue' => env('USER_SERVICE_SYNC_QUEUE', 'default'),
        'retry_times' => env('USER_SERVICE_SYNC_RETRY', 3),
        'retry_delay' => env('USER_SERVICE_SYNC_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache Configuration
    |--------------------------------------------------------------------------
    */
    'token' => [
        'cache_prefix' => 'user_service_tokens',
        'cache_ttl' => env('USER_SERVICE_TOKEN_CACHE_TTL', 60 * 60 * 24 * 30), // 30 days
        'buffer_seconds' => 30, // Refresh token 30 seconds before expiry
    ],

    /*
    |--------------------------------------------------------------------------
    | Fields Mapping: Keycloak -> User Service
    |--------------------------------------------------------------------------
    | Map Keycloak user attributes to User Service fields
    */
    'field_mapping' => [
        'id' => 'sub',                      // Keycloak subject ID
        'email' => 'email',
        'username' => 'preferred_username',
        'fullName' => 'name',
        'phoneNumber' => 'phone_number',
    ],

];
