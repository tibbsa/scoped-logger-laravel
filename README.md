# Fine-grained logging level management for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tibbsa/scoped-logger-laravel.svg?style=flat-square)](https://packagist.org/packages/tibbsa/scoped-logger-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/tibbsa/scoped-logger-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tibbsa/scoped-logger-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/tibbsa/scoped-logger-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/tibbsa/scoped-logger-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/tibbsa/scoped-logger-laravel.svg?style=flat-square)](https://packagist.org/packages/tibbsa/scoped-logger-laravel)

When troubleshooting specific issues in a Laravel application, it is often helpful to have increased logging
visibility regarding a specific portion of your application. However, if you increase your log level, then
you will also increase the log traffic from all _other_ parts of the application at the same time. While
logging channels offer some flexibility in separating different types of log entries, they are cumbersome and
must all be pre-configured before they can be used.

This package adds the ability to define different logging levels based on the "scope" of a particular log entry.
Scopes can be explicitly defined when logging, or automatically determined based on the calling class.

## Features

- 🎯 **Scope-based log levels** - Set different log levels for different parts of your application
- 🔍 **Wildcard pattern matching** - Use `App\Services\*` or `payment.*` to match multiple scopes
- 📊 **Smart pattern priority** - Most specific pattern wins (exact > longer > fewer wildcards)
- 🔄 **Automatic scope detection** - Auto-detect scope from calling class (FQCN, property, or method)
- 🚫 **Scope suppression** - Completely silence logs from specific scopes
- ⚠️ **Unknown scope detection** - Configurable handling for unconfigured scopes (exception, log warning, or ignore)
- 📺 **Per-channel configurations** - Different scope levels for different log channels
- 🔀 **Multiple scopes** - Log with multiple scopes using "most verbose wins" strategy
- ⚡ **Runtime modification** - Temporarily override scope levels without config changes
- 🎛️ **Conditional logging** - Use closures for dynamic levels based on environment, time, etc.
- 🐛 **Debug mode** - Detailed scope resolution info for troubleshooting
- 🪝 **Laravel integration** - Works seamlessly with `Log` facade and `logger()` helper
- ⚡ **Zero config required** - Works out of the box with sensible defaults

## Installation

Install the package via composer:

```bash
composer require tibbsa/scoped-logger-laravel
```

Publish the config file (optional):

```bash
php artisan vendor:publish --tag="scoped-logger-config"
```

## Quick Start

Configure scopes in `config/scoped-logger.php`:

```php
return [
    'default_level' => 'info',

    'scopes' => [
        'payment' => 'debug',      // Verbose logging for payments
        'auth' => 'error',         // Only errors for authentication
        'reporting' => 'debug',    // Detailed logs for reports
        'chatty-vendor' => false,  // Completely suppress
    ],
];
```

Use in your application:

```php
// Explicit scope
Log::scope('payment')->debug('Processing payment', ['amount' => 100]);
// ✅ Logs because 'payment' scope allows 'debug'

Log::scope('auth')->info('User logged in');
// ❌ Silently dropped because 'auth' requires 'error' or higher

Log::info('General application message');
// ✅ Logs at default level
```

## Usage

### Explicit Scopes

The most straightforward way to use scoped logging:

```php
use Illuminate\Support\Facades\Log;

// Using Log facade
Log::scope('payment')->debug('Payment details', $data);
Log::scope('api')->info('API request', $request);

// Using logger helper
logger()->scope('auth')->warning('Failed login attempt');

// Chain with other methods
Log::scope('reporting')
    ->withContext(['user_id' => 123])
    ->info('Report generated');
```

### Automatic Scope Detection

Scoped Logger can automatically detect the scope from the calling class:

```php
namespace App\Services;

class PaymentService
{
    // Option 1: Use class FQCN as scope
    // Configure: 'App\Services\PaymentService' => 'debug'

    // Option 2: Define static property
    protected static string $log_scope = 'payment';

    // Option 3: Define static method
    public static function getLogScope(): string
    {
        return 'payment';
    }

    public function processPayment()
    {
        // Automatically uses 'payment' scope
        Log::debug('Processing payment...');
    }
}
```

### Scope Priority

Scopes are resolved in this order:

1. **Explicit scope** - `Log::scope('payment')`
2. **Class FQCN** - If the calling class FQCN is configured as a scope
3. **Class property/method** - Static property `$log_scope` or method `getLogScope()`
4. **Default level** - Falls back to `default_level` config

### Pattern Matching

Use wildcard patterns to match multiple scopes with a single configuration:

```php
'scopes' => [
    // Match all service classes
    'App\\Services\\*' => 'debug',

    // Match specific namespaces (more specific wins)
    'App\\Services\\Payment\\*' => 'info',

    // Dot notation patterns
    'payment.*' => 'debug',

    // Suppress all vendor logs
    'vendor.*' => false,
],
```

**Supported Wildcards:**
- `*` - Matches any characters (including none)
- `?` - Matches exactly one character

**Pattern Specificity:**

When multiple patterns match a scope, the most specific pattern wins:

1. **Exact matches** (no wildcards) are most specific
2. **Longer patterns** are more specific than shorter ones
3. **Fewer wildcards** make patterns more specific

```php
'scopes' => [
    'App\\*' => 'warning',                    // Least specific
    'App\\Services\\*' => 'info',             // More specific
    'App\\Services\\Payment\\*' => 'debug',   // Most specific
    'App\\Services\\PaymentService' => 'error', // Exact (highest priority)
],

Log::scope('App\\Services\\Payment\\StripeService')->debug('...');
// Uses 'App\\Services\\Payment\\*' (most specific pattern match)

Log::scope('App\\Services\\PaymentService')->debug('...');
// Uses exact match 'App\\Services\\PaymentService'
```

### Suppressing Scopes

Set a scope to `false` to completely suppress all logs from that scope:

```php
'scopes' => [
    'noisy-vendor' => false,
    'debug-toolbar' => false,
    'vendor.*' => false,  // Suppress all vendor packages
],
```

```php
Log::scope('noisy-vendor')->emergency('Critical error!');
// ❌ Completely suppressed, even emergency logs
```

### Unknown Scope Handling

By default, using an unconfigured scope throws an exception. This helps catch typos and configuration mistakes:

```php
'scopes' => [
    'payment' => 'debug',
],

Log::scope('paymnet')->info('typo!');
// ❌ Throws UnknownScopeException - helps you catch the typo
```

You can configure how unknown scopes are handled:

```php
// config/scoped-logger.php
'unknown_scope_handling' => 'exception',  // Default - throw exception
// 'unknown_scope_handling' => 'log',     // Log warning and continue with default level
// 'unknown_scope_handling' => 'ignore',  // Silently use default level
```

**Handling Options:**

- **`exception`** (default): Throws `UnknownScopeException` - best for catching configuration errors
- **`log`**: Logs a warning and processes the log with the default level
- **`ignore`**: Silently uses the default level

**What counts as "known":**
- Exact match in scopes configuration
- Matches a wildcard pattern
- Has a runtime override set
- Auto-detected scopes that return `null` (use default level)

**Environment Variable:**
```bash
SCOPED_LOG_UNKNOWN_SCOPE=log  # or 'exception', 'ignore'
```

## Configuration

### Disabling Scoped Logging

You can completely disable scoped logging by setting `SCOPED_LOG_ENABLED=false` in your `.env` file. When disabled:

- ✅ **All logs pass through** to the underlying Laravel logger without any filtering
- ❌ **No scope-based filtering** - all configured scope levels are ignored
- ❌ **No scope added to context** - the `scope` key won't appear in log context
- ❌ **No metadata added** - caller metadata (file, line, class) won't be added
- ❌ **No debug info added** - scope resolution debug info won't be added
- ❌ **No unknown scope checking** - unknown scopes won't throw exceptions or log warnings
- ✅ **Shared context preserved** - context from `withContext()` is still merged
- ✅ **Underlying channel level applies** - Laravel's channel log level still filters

**Use this when:**
- You want to completely bypass scoped logging
- You're troubleshooting and want to see all logs regardless of scope configuration
- You want Laravel's default logging behavior

```php
// .env
SCOPED_LOG_ENABLED=false  // Bypass all scoped logging features
```

### Full Configuration Options

All available configuration options:

```php
return [
    // Master switch - set to false to disable scoped logging globally
    'enabled' => env('SCOPED_LOG_ENABLED', true),

    // Default level when no scope matches
    'default_level' => env('SCOPED_LOG_DEFAULT_LEVEL', 'info'),

    // Scope definitions (supports exact matches, wildcards, closures, and false for suppression)
    'scopes' => [
        'payment' => 'debug',
        'auth' => 'warning',
        'App\\Services\\MailchimpApi' => 'debug',
        'App\\Services\\*' => 'info',                // Wildcard pattern
        'vendor.*' => false,                         // Suppress completely
        'api' => fn() => app()->environment('local') ? 'debug' : 'error',  // Closure
    ],

    // How to handle unknown/unconfigured scopes (exception, log, or ignore)
    'unknown_scope_handling' => env('SCOPED_LOG_UNKNOWN_SCOPE', 'exception'),

    // Per-channel scope configurations (override global scopes for specific channels)
    'channel_scopes' => [
        'daily' => [
            'payment' => 'debug',
            'api' => 'info',
        ],
        'slack' => [
            'payment' => 'error',
            'api' => 'error',
        ],
    ],

    // Auto-detection settings
    'auto_detection' => [
        'enabled' => env('SCOPED_LOG_AUTO_DETECT', true),
        'property' => 'log_scope',        // Property/method name to check
        'stack_depth' => 10,               // How deep to traverse stack
        'skip_vendor' => true,             // Skip vendor classes
        'skip_paths' => ['/vendor/', '/bootstrap/'],
    ],

    // List of channels that should NOT use scoped logging
    // By default, all channels use scoped logging (global by default)
    'disabled_channels' => [
        // 'slack',
        // 'sentry',
    ],

    // Add scope identifier to log context
    'include_scope_in_context' => env('SCOPED_LOG_INCLUDE_SCOPE', true),
    'scope_context_key' => 'scope',

    // Add caller metadata (file, line, class, function) to log context
    'include_metadata' => env('SCOPED_LOG_INCLUDE_METADATA', false),

    // Metadata extraction settings
    'metadata_skip_vendor' => true,       // Skip vendor files when finding caller
    'metadata_relative_paths' => true,    // Show paths relative to base_path()
    'metadata_base_path' => null,         // Base path for relative paths (null = base_path())

    // Debug mode - adds detailed scope resolution info to context (performance impact)
    'debug_mode' => env('SCOPED_LOG_DEBUG', false),
];
```

## ⚠️ Important: Channel Log Levels

**The underlying Laravel channel log level acts as a floor for all logged events.**

If you're using scoped logging on a channel, that channel should be configured with the **lowest log level** you need (typically `debug`), otherwise the channel will filter out logs before scoped logging can process them.

### Example Problem

```php
// config/scoped-logger.php
'scopes' => [
    'payment' => 'debug',  // You want debug logs for payments
],

// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'level' => 'warning',  // ⚠️ PROBLEM: Channel filters out debug/info
    ],
],
```

```php
Log::scope('payment')->debug('Payment processing');
// ❌ DROPPED: Scoped logger allows it, but channel level is 'warning'
```

### Solution

Set your channel to `debug` level and let scoped logging handle the filtering:

```php
// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'level' => 'debug',  // ✅ Let scoped logger control filtering
    ],
],
```

Now scoped logging has full control over what gets logged.

### Why This Happens

Logging flows through two filters:

1. **Scoped Logger** - Filters based on scope configuration
2. **Channel Driver** - Filters based on channel `level` config

Both must allow the log through. The channel level is a hard floor that cannot be overridden by scoped logging.

## Runtime Modification

Temporarily override scope levels at runtime without changing configuration:

```php
// Temporarily increase logging for debugging
Log::setRuntimeLevel('payment', 'debug');
Log::scope('payment')->debug('Now logging'); // ✅ Logs

// Clear override
Log::clearRuntimeLevel('payment');

// Temporarily suppress a noisy scope
Log::setRuntimeLevel('chatty-service', false);

// Clear all overrides
Log::clearAllRuntimeLevels();
```

Runtime overrides:
- Take precedence over configured levels and pattern matches
- Persist only for the current request (in-memory)
- Perfect for temporary debugging without config changes

## Conditional Logging

Use closures for dynamic log levels based on environment, time, feature flags, or any custom logic:

```php
// config/scoped-logger.php
'scopes' => [
    // Environment-based
    'api' => fn() => app()->environment('local') ? 'debug' : 'error',

    // Time-based (verbose logging during off-peak hours)
    'batch-import' => fn() => now()->hour >= 2 && now()->hour < 6 ? 'debug' : 'info',

    // Feature flag-based
    'experimental' => fn() => config('features.verbose_experimental') ? 'debug' : 'warning',

    // Custom logic
    'performance' => fn() => app('metrics')->isUnderLoad() ? 'error' : 'info',
],
```

Closures are evaluated on each log call, allowing real-time adjustments based on current conditions.

## Per-Channel Scope Configurations

Configure different log levels for the same scope across different channels:

```php
// config/scoped-logger.php
'scopes' => [
    // Global defaults
    'payment' => 'error',
    'api' => 'warning',
],

'channel_scopes' => [
    // Verbose logging on daily file
    'daily' => [
        'payment' => 'debug',
        'api' => 'info',
    ],

    // Errors only on Slack
    'slack' => [
        'payment' => 'error',
        'api' => 'error',
    ],
],
```

Channel-specific scopes override global scopes. If a scope isn't defined for a channel, it falls back to the global configuration.

```php
Log::channel('daily')->scope('payment')->debug('Processing payment');
// ✅ Logs (daily channel allows debug for payment)

Log::channel('slack')->scope('payment')->debug('Processing payment');
// ❌ Dropped (slack channel requires error level)
```

## Multiple Scopes

Log with multiple scopes simultaneously by passing an array to `scope()`. The package uses a "most verbose wins" strategy:

```php
Log::scope(['payment', 'api'])->debug('Payment via API');
```

Rules:
- Uses the **lowest (most verbose) log level** among all scopes
- If **any scope is suppressed** (`false`), the entire log is suppressed
- All scopes are included in context as a comma-separated string

```php
// config/scoped-logger.php
'scopes' => [
    'payment' => 'debug',  // Most verbose
    'api' => 'error',      // Least verbose
],

Log::scope(['payment', 'api'])->debug('test');
// ✅ Logs because payment allows debug (most verbose wins)

Log::scope(['payment', 'api'])->info('test');
// ✅ Logs because payment allows info

Log::scope('api')->debug('test');
// ❌ Dropped because api requires error level
```

## Debug Mode

Enable debug mode to see detailed scope resolution information in log context:

```bash
# .env
SCOPED_LOG_DEBUG=true
```

When enabled, each log entry includes:

```php
[
    'scoped_logger_debug' => [
        'resolved_scope' => 'payment',
        'log_level' => 'debug',
        'configured_level' => 'debug',
        'resolution_method' => 'explicit (scope() method)',
        'runtime_override' => 'no',
        'matched_pattern' => 'App\\Services\\*',  // If applicable
    ],
]
```

⚠️ **Warning**: Debug mode adds overhead. Use only for troubleshooting scope resolution issues.

## Artisan Commands

### List all configured scopes

```bash
php artisan scoped-logger:list

# Sort by level instead of name
php artisan scoped-logger:list --sort=level
```

Displays a table of all scopes, their levels, and whether they're patterns.

### Test scope resolution

```bash
php artisan scoped-logger:test payment

# Test with a specific log level
php artisan scoped-logger:test payment --level=debug
```

Shows:
- What pattern (if any) matches the scope
- What log level applies
- Whether each PSR-3 level would log or be dropped

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities


## Credits

- [Anthony Tibbs](https://github.com/tibbsa)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
