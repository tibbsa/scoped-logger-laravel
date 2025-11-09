# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package that provides fine-grained logging level management. It allows developers to define different logging levels based on "scopes" (specific portions of the application) without increasing log traffic across the entire application. Scopes can be developer-defined or auto-determined based on the calling class.

**Package Name:** `tibbsa/scoped-logger-laravel`
**Namespace:** `TibbsA\ScopedLogger`

## Development Commands

### Testing
```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run tests with CI formatting (as used in GitHub Actions)
vendor/bin/pest --ci
```

### Code Quality
```bash
# Run static analysis
composer analyse
# Or directly:
vendor/bin/phpstan

# Format code (auto-fixes styling issues)
composer format
# Or directly:
vendor/bin/pint
```

### Package Setup
```bash
# After composer install/update, discover package
composer run prepare
```

## Architecture

### Package Structure
This is a Laravel package built using Spatie's `laravel-package-tools`. The core architecture follows Laravel package conventions:

- **Service Provider** (`ScopedLoggerServiceProvider`): Registers the package with Laravel, publishes config file
- **Facade** (`Facades\ScopedLogger`): Provides static access to the main ScopedLogger class
- **Core Class** (`ScopedLogger`): Main implementation (currently skeletal, to be developed)
- **Config** (`config/scoped-logger.php`): Defines default logging level and scope-specific overrides

### Configuration System
The package uses a scope-based configuration approach where:
- `default_level`: Global default log level when no scope matches
- `scopes`: Array mapping scope names (strings or class names) to specific log levels
- Supported levels: debug, info, notice, warning, error, critical, alert, emergency

### Testing Setup
Uses Orchestra Testbench for Laravel package testing with Pest as the test framework. All tests extend `TestCase` which provides the necessary Laravel environment setup.

## Testing Environment

- **Framework**: Pest (PHP testing framework)
- **Test bench**: Orchestra Testbench (provides Laravel environment for package testing)
- **Base class**: `TibbsA\ScopedLogger\Tests\TestCase`
- **Architecture tests**: Located in `tests/ArchTest.php`
- **PHP versions tested**: 8.3, 8.4
- **Laravel versions tested**: 11.*

## Static Analysis

- **Tool**: PHPStan with Larastan
- **Level**: max (strictest analysis)
- **Config**: `phpstan.neon.dist`
- **Baseline**: `phpstan-baseline.neon` (tracks existing issues)
- **Checks**: Octane compatibility, model properties
- **Analyzed paths**: `src/`, `config/`

## Code Style

- **Tool**: Laravel Pint
- **Auto-formatting**: GitHub Actions automatically formats and commits code style fixes on push
- Pint follows Laravel's opinionated code style conventions

## Requirements

- PHP: ^8.3
- Laravel: ^10.0 || ^11.0 (via illuminate/contracts)
- Composer package type: Laravel package with auto-discovery
