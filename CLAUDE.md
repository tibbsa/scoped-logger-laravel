# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package that provides fine-grained logging level management. It allows developers to define different logging levels based on "scopes" (specific portions of the application) without increasing log traffic across the entire application.

**Package Name:** `tibbs/scoped-logger-laravel`
**Namespace:** `Tibbs\ScopedLogger`
**Requirements:** PHP 8.3+, Laravel 12.11.0+

## Development Environment

This is a **Windows development environment** using **Laravel Herd**. All PHP commands must be run through Herd's PHP binary:

```bash
"$HOME/.config/herd/bin/php.bat" <command>
```

The approved Bash commands in the hook configuration already handle this, so use composer scripts when possible.

## Development Commands

### Testing
```bash
# Run all tests (uses Herd PHP automatically)
composer test

# Run tests with coverage (requires pcov)
composer test-coverage

# Run specific test file
"$HOME/.config/herd/bin/php.bat" vendor/bin/pest tests/ScopedLoggerTest.php

# Run tests matching a filter
"$HOME/.config/herd/bin/php.bat" vendor/bin/pest --filter=ScopeResolver
```

### Code Quality
```bash
# Run static analysis (PHPStan)
composer analyse

# Check code style without fixing
vendor/bin/pint --test

# Format code (auto-fixes styling issues)
composer format
```

### Artisan Commands (Package)
```bash
# List all configured scopes
"$HOME/.config/herd/bin/php.bat" artisan scoped-logger:list

# Test scope resolution for a specific scope
"$HOME/.config/herd/bin/php.bat" artisan scoped-logger:test payment --level=debug
```

## Architecture Overview

### Core Flow: How Scoped Logging Works

1. **Service Provider Extension** (`ScopedLoggerServiceProvider:32-34`):
   - Extends Laravel's `LogManager` with `ScopedLogManager`
   - Wraps all log channels unless explicitly disabled

2. **Channel Wrapping** (`ScopedLogManager:23-40`):
   - When `Log::channel()` is called, returns `ScopedLogger` instance
   - Checks if channel is enabled for scoped logging (respects `disabled_channels` config)
   - Passes through to original Laravel logger if disabled

3. **Scope Resolution Chain** (`ScopedLogger:246-315`):
   - **Explicit scopes**: Set via `Log::scope('payment')` (highest priority)
   - **Runtime overrides**: Temporary level changes via `setRuntimeLevel()`
   - **Pattern matching**: Wildcards like `App\Services\*` (via `PatternMatcher`)
   - **Auto-detection**: Walks stack trace to find calling class (via `ScopeResolver`)
   - **Default level**: Falls back to `default_level` config

4. **Log Filtering** (`ScopedLogger:422-443`):
   - Compares log level against configured scope level
   - Uses PSR-3 severity hierarchy (debug=0 → emergency=7)
   - Silently drops logs below threshold
   - Completely suppresses if scope is configured as `false`

### Key Components

**ScopedLogManager** (`src/ScopedLogManager.php`)
- Decorates Laravel's `LogManager`
- Intercepts `channel()` and `driver()` calls
- Returns wrapped `ScopedLogger` instances

**ScopedLogger** (`src/ScopedLogger.php`)
- Implements `LoggerInterface` (PSR-3)
- Main filtering logic in `log()` method
- Manages runtime level overrides
- Handles multiple scopes with "most verbose wins" strategy
- Adds metadata and debug context when configured

**ScopeResolver** (`src/Support/ScopeResolver.php`)
- Auto-detects scope from calling class via stack trace analysis
- Checks for static `$log_scope` property or `getLogScope()` method
- Skips framework and vendor classes to find actual caller
- Three resolution strategies: explicit → FQCN → property/method

**PatternMatcher** (`src/Support/PatternMatcher.php`)
- Compiles wildcard patterns (`*`, `?`) into regex
- Finds best match using specificity rules: exact > longer > fewer wildcards
- Caches compiled patterns and match results for performance

**Configuration** (`src/Configuration/Configuration.php`)
- Immutable value object created from config array
- Type-safe accessor methods for all config options
- Supports per-channel scope overrides
- Handles closures for conditional log levels

**Validator** (`src/Configuration/Validator.php`)
- Validates config at boot time (runs in `packageBooted()`)
- Checks log levels, scope patterns, unknown scope handling modes
- Throws `InvalidScopeConfigurationException` for invalid configs

### Pattern Matching Specificity

When multiple patterns match a scope, specificity is determined by:

1. **Exact matches** (no wildcards) always win
2. **Length**: Longer patterns are more specific
3. **Wildcard count**: Fewer wildcards = more specific

Example:
```php
'App\Services\PaymentService' => 'error'        // Exact: highest priority
'App\Services\Payment\*' => 'debug'             // Longer, more specific
'App\Services\*' => 'info'                      // Shorter, less specific
'App\*' => 'warning'                            // Shortest, least specific
```

### Multiple Scopes Strategy

When logging with `Log::scope(['payment', 'api'])`:
- Uses **lowest (most verbose) level** among all scopes
- If **any** scope is suppressed (`false`), entire log is suppressed
- All scope names joined with `, ` in context

### Auto-Detection Hierarchy

For auto-detected scopes (when no explicit `scope()` call):

1. Check if calling class FQCN is configured as scope
2. Check for static `$log_scope` property on class
3. Check for static `getLogScope()` method on class
4. Return `null` (uses `default_level`)

### Unknown Scope Handling

Configured via `unknown_scope_handling` (default: `'exception'`):

- **`exception`**: Throws `UnknownScopeException` - helps catch typos early
- **`log`**: Logs warning and continues with default level
- **`ignore`**: Silently uses default level

A scope is "known" if:
- Has exact match in config
- Matches a wildcard pattern
- Has runtime override set
- Is `null` from auto-detection (uses default)

## Configuration System

The package uses a cascading configuration approach:

1. **Global config** (`config/scoped-logger.php`) defines default scopes
2. **Per-channel overrides** in `channel_scopes` override global for specific channels
3. **Runtime overrides** via `setRuntimeLevel()` override everything (in-memory only)

## Testing Architecture

- **Framework**: Pest with Orchestra Testbench
- **Base class**: `Tibbs\ScopedLogger\Tests\TestCase`
- **Test fixtures**: Located in `tests/Fixtures/` for testing auto-detection
- **Coverage target**: 80% minimum (enforced in CI for PHP 8.4)

### Test Organization

- **Integration tests**: `*IntegrationTest.php` - test full Log facade integration
- **Unit tests**: Test individual components (ScopeResolver, PatternMatcher, etc.)
- **Feature tests**: Test specific features (runtime modification, multiple scopes, etc.)
- **Command tests**: Test Artisan commands in `tests/Commands/`

## Static Analysis Notes

- **Level**: `max` (strictest)
- **Tools**: PHPStan with Larastan, PHPUnit, and deprecation rules
- **Octane compatibility**: Checked via `checkOctaneCompatibility: true`
- **Allowed env() calls**: Only in `config/` files (see `phpstan.neon.dist:14`)
- **No baseline**: Package uses inline ignores rather than baseline file

## Important Constraints

1. **Channel log levels act as a floor**: If a channel's `level` config is `warning`, scoped logger cannot log `debug` or `info` through that channel, even if scope allows it. Set channel to `debug` level and let scoped logger handle filtering.

2. **Windows paths**: When writing code that manipulates file paths, remember this is a Windows environment (use `DIRECTORY_SEPARATOR` or normalize paths).

3. **Test isolation**: Each test should clear runtime overrides and explicit scopes to prevent bleed-through.
