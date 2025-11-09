<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Scoped Logger
    |--------------------------------------------------------------------------
    | Master switch to enable or disable the scoped logger functionality.
    | When disabled, all logs pass through to Laravel's default logger.
    */
    'enabled' => env('SCOPED_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Logging Level
    |--------------------------------------------------------------------------
    | This will be used if a scope does not have a specific override.
    | Valid values: debug, info, notice, warning, error, critical, alert, emergency
    */
    'default_level' => env('SCOPED_LOG_DEFAULT_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Scopes and Their Logging Levels
    |--------------------------------------------------------------------------
    | Here you can define custom logging levels for specific scopes.
    | The key is the scope name (string). The value is one of
    | Laravel's log levels: debug, info, notice, warning,
    | error, critical, alert, emergency
    |
    | Set a scope to false to completely suppress all logs from that scope.
    |
    | Supports wildcard patterns:
    | - * matches any characters (e.g., 'App\Services\*' matches all service classes)
    | - ? matches a single character (e.g., 'test?' matches 'test1', 'testa')
    |
    | Pattern specificity (most to least specific):
    | 1. Exact matches (no wildcards)
    | 2. Longer patterns
    | 3. Patterns with fewer wildcards
    */
    'scopes' => [
        // Example exact matches:
        // 'auth' => 'debug',
        // 'payment' => 'warning',
        // 'App\\Services\\MailchimpApi' => 'debug',

        // Example wildcard patterns:
        // 'App\\Services\\*' => 'debug',           // All service classes
        // 'App\\Services\\Payment\\*' => 'info',   // All payment services (more specific)
        // 'payment.*' => 'debug',                  // Dot-notation patterns
        // 'vendor.*' => false,                     // Suppress all vendor logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Unknown Scope Handling
    |--------------------------------------------------------------------------
    | How to handle logs using scopes that are not configured.
    |
    | Options:
    | - 'exception': Throw UnknownScopeException (helps catch configuration typos)
    | - 'log': Log a warning and allow the log with default level
    | - 'ignore': Silently use default level (legacy behavior)
    |
    | Note: Scopes matching wildcard patterns are considered "known".
    */
    'unknown_scope_handling' => env('SCOPED_LOG_UNKNOWN_SCOPE', 'exception'),

    /*
    |--------------------------------------------------------------------------
    | Per-Channel Scope Configuration
    |--------------------------------------------------------------------------
    | Override scope levels for specific channels. These take precedence over
    | the global 'scopes' configuration above.
    |
    | Example: Be verbose on 'daily' but only errors on 'slack'
    */
    'channel_scopes' => [
        // 'daily' => [
        //     'payment' => 'debug',
        //     'api' => 'info',
        // ],
        // 'slack' => [
        //     'payment' => 'error',
        //     'api' => 'error',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Detection Settings
    |--------------------------------------------------------------------------
    | Configure how scopes are automatically detected from the calling context.
    */
    'auto_detection' => [
        /*
        | Enable automatic scope detection from calling class
        */
        'enabled' => env('SCOPED_LOG_AUTO_DETECT', true),

        /*
        | Property or method name to check on the calling class for scope identifier
        | Can be a property name (e.g., 'log_scope') or method name (e.g., 'getLogScope')
        */
        'property' => 'log_scope',

        /*
        | Maximum depth to traverse the stack trace when looking for calling class
        | Higher values may impact performance but catch more cases
        */
        'stack_depth' => 10,

        /*
        | Skip vendor classes when traversing stack trace
        | This helps identify the actual application class making the log call
        */
        'skip_vendor' => true,

        /*
        | Additional paths to skip when traversing stack trace
        | Useful for skipping framework classes or other base classes
        */
        'skip_paths' => [
            '/vendor/',
            '/bootstrap/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Disabled Channels
    |--------------------------------------------------------------------------
    | List of channel names that should NOT use scoped logging.
    | These channels will use Laravel's default logging behavior.
    | By default, all channels use scoped logging (global by default).
    */
    'disabled_channels' => [
        // Example:
        // 'slack',
        // 'sentry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Scope in Context
    |--------------------------------------------------------------------------
    | When true, the resolved scope identifier will be added to the log context
    | under the key specified in 'scope_context_key'.
    | This helps with filtering and debugging.
    */
    'include_scope_in_context' => env('SCOPED_LOG_INCLUDE_SCOPE', true),

    /*
    |--------------------------------------------------------------------------
    | Scope Context Key
    |--------------------------------------------------------------------------
    | The key name used when adding scope to log context.
    */
    'scope_context_key' => 'scope',

    /*
    |--------------------------------------------------------------------------
    | Include Metadata
    |--------------------------------------------------------------------------
    | When true, adds caller metadata (file, line, class, function) to log context.
    | Useful for debugging but may impact performance.
    */
    'include_metadata' => env('SCOPED_LOG_INCLUDE_METADATA', false),

    /*
    |--------------------------------------------------------------------------
    | Metadata Configuration
    |--------------------------------------------------------------------------
    | Configure how metadata is extracted and formatted.
    */
    'metadata_skip_vendor' => true,          // Skip vendor files when finding caller
    'metadata_relative_paths' => true,       // Show paths relative to base_path()
    'metadata_base_path' => null,            // Base path for relative paths (null = base_path())

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | When enabled, adds detailed scope resolution information to log context.
    | This helps troubleshoot why a particular scope/level was chosen.
    | WARNING: This adds significant overhead and should only be used for debugging.
    */
    'debug_mode' => env('SCOPED_LOG_DEBUG', false),

];
