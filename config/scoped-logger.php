<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Logging Level
    |--------------------------------------------------------------------------
    | This will be used if a scope does not have a specific override.
    */
    'default_level' => env('SCOPED_LOG_DEFAULT_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Scopes and Their Logging Levels
    |--------------------------------------------------------------------------
    | Here you can define custom logging levels for specific scopes.
    | The key is the scope name (string). The value is one of
    | Laravel’s log levels: debug, info, notice, warning,
    | error, critical, alert, emergency
    */
    'scopes' => [
        // Example:
        // 'auth' => 'debug',
        // 'payment' => 'warning',
        // 'App\\Services\\MailchimpApi' => 'debug',
        // ...
    ],

];
