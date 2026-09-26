# Changelog

## v2.0 (2026-09-26)

### Breaking changes

- **On-demand channels are filtered.** `Log::stack()` and `Log::build()` channels are now filtered by scope and support `scope()`. They match `channel_scopes`/`disabled_channels` as the stack's name (default `stack`) or `ondemand`. Previously they bypassed the package entirely. ([#34](https://github.com/tibbsa/scoped-logger-laravel/pull/34))
- **Disabled channels return `PassThroughScopedLogger`.** When scoped logging is disabled globally or for a channel, `Log::channel()` returns a `PassThroughScopedLogger` instead of Laravel's `Illuminate\Log\Logger`. It forwards PSR-3 calls and context to Laravel's logger, treats the scoped API (`scope()`, `setRuntimeLevel()`, …) as no-ops, and forwards other calls via `__call()`. `Log::scope('x')->info(...)` therefore works instead of erroring when the package is off. ([#28](https://github.com/tibbsa/scoped-logger-laravel/pull/28))
- **Return types use `ScopedLoggerContract`.** `Log::channel()`, `driver()`, `stack()`, `build()`, `scope()`, `setRuntimeLevel()`, `clearRuntimeLevel()` and `clearAllRuntimeLevels()` now declare `ScopedLoggerContract` (implemented by both `ScopedLogger` and `PassThroughScopedLogger`). The manager methods no longer throw `LogicException` when the default channel is disabled. ([#28](https://github.com/tibbsa/scoped-logger-laravel/pull/28))
- `ScopedLogger::withoutContext()` accepts an optional `?array $keys` argument, matching Laravel. Subclasses overriding it must add the parameter. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))

### Added

- Enum channel names (backed or pure `UnitEnum`) in `Log::channel()`, `driver()`, `forgetChannel()` and `setDefaultDriver()`, on both Laravel 12 and 13. ([#33](https://github.com/tibbsa/scoped-logger-laravel/pull/33))
- `Tibbs\ScopedLogger\Contracts\ScopedLoggerContract` and `Tibbs\ScopedLogger\PassThroughScopedLogger`. ([#28](https://github.com/tibbsa/scoped-logger-laravel/pull/28))
- Pest 5 support in the dev toolchain; CI now also tests PHP 8.5. ([#31](https://github.com/tibbsa/scoped-logger-laravel/pull/31))

### Fixed

- `Log::extend()` custom drivers were never registered with Laravel's log manager, so custom channels fell back to the emergency logger. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))
- `Log::shareContext()`, `sharedContext()` and `flushSharedContext()` had no effect on any channel. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))
- `Log::withoutContext()` did nothing, so context leaked between requests under Octane. `Log::setApplication()` left Laravel's log manager holding a stale application under Octane. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))
- `Log::forgetChannel()` neither rebuilt the channel nor reset its scoped state. `Log::getChannels()` always returned an empty list. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))
- `withoutContext($keys)` on a channel cleared all context instead of only the given keys. ([#32](https://github.com/tibbsa/scoped-logger-laravel/pull/32))

### Upgrading from 1.x

1. Update your constraint: `composer require tibbs/scoped-logger-laravel:^2.0`.
2. If you relied on `Log::stack()`/`Log::build()` being unfiltered, add `stack`, `ondemand`, or your stack names to `disabled_channels`. Their scoped API stays usable either way.
3. Replace `ScopedLogger` type hints on values returned by the `Log` facade with `ScopedLoggerContract`.
4. Replace `instanceof Illuminate\Log\Logger` checks on `Log::channel()` results. Disabled channels now return `PassThroughScopedLogger`.
5. Remove any `try`/`catch (LogicException)` around `Log::scope()` or runtime-level calls made while the default channel is disabled; they no longer throw.

## v1.2 (2026-04-16)

- Fixed PHPStan/Larastan errors for `ScopedLogManager` methods ([#25](https://github.com/tibbsa/scoped-logger-laravel/pull/25))
- Fixed: cache `ScopedLogger` wrappers per channel to preserve runtime levels and context ([#26](https://github.com/tibbsa/scoped-logger-laravel/pull/26))
- Removed deprecated `setAccessible()` calls from `ScopeResolver` ([#27](https://github.com/tibbsa/scoped-logger-laravel/pull/27))

## v1.1 (2026-03-22)

- Added Laravel 13 compatibility ([#19](https://github.com/tibbsa/scoped-logger-laravel/pull/19))

## v1.0 (2026-02-02)

- First stable release
- README updates ([#14](https://github.com/tibbsa/scoped-logger-laravel/pull/14))

## v0.30 (2026-01-09)

- `scoped-logger:list` and `scoped-logger:test` handle per-channel scopes ([#15](https://github.com/tibbsa/scoped-logger-laravel/pull/15))
- Fixed the `ScopedLogger` facade's `getFacadeAccessor()` return value ([#18](https://github.com/tibbsa/scoped-logger-laravel/pull/18))

## v0.20.0 (2025-11-25)

- README updates
- Added performance test suite for impact evaluation
- Initial release to Packagist

## v0.10.0 (2025-11-10)

- Initial tagged version for use in internal projects



