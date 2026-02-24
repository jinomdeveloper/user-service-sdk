<?php

// config for Jinom/UserServiceSdk
return [

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
