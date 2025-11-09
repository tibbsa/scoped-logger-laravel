# Scoped Logger Development Roadmap

This document tracks planned features and implementation phases for the Scoped Logger package.

## Phase 1: Core Foundation (MVP) ✅ COMPLETED

**Goal**: Get basic scoped logging working end-to-end

- [x] Configuration System
  - [x] Enhance `config/scoped-logger.php` with complete structure
  - [x] Add validation for scope names and log levels
  - [x] Support ENV variable overrides for common scopes

- [x] Scope Resolver
  - [x] Create `ScopeResolver` class to determine scope for a log entry
  - [x] Implement hierarchy: explicit → class FQCN → class property/method → default
  - [x] Stack trace inspection to find calling class
  - [x] Configurable stack depth and vendor filtering

- [x] Logger Wrapper/Proxy
  - [x] Create custom logger that wraps Laravel's logger
  - [x] Implement `scope()` fluent method
  - [x] Intercept all log level methods (debug, info, warning, etc.)
  - [x] Check scope level before passing to underlying logger
  - [x] Maintain all context, shared state, and other Laravel logger features

- [x] Service Provider Enhancement
  - [x] Register custom logger
  - [x] Bind to Laravel's logging system
  - [x] Make available via facade and helper
  - [x] Config validation on boot

- [x] Basic Tests
  - [x] Test scope resolution logic (9 tests)
  - [x] Test level filtering (16 tests)
  - [x] Test fluent API
  - [x] Test auto-detection from class
  - [x] Test config validation (4 tests)

**Summary**: All 30 tests passing with 51 assertions. PHPStan analysis passing.

## Phase 2: Essential Features ✅ COMPLETED

**Goal**: Production-ready features

- [x] Pattern Matching
  - [x] Wildcard support (`App\Services\*`, `payment.*`)
  - [x] Pattern compilation and caching
  - [x] Scope inheritance/hierarchy resolution
  - [x] Smart specificity: exact > longer > fewer wildcards

- [x] Performance Optimization
  - [x] Cache compiled patterns on initialization
  - [x] Cache scope match results per request
  - [x] Pattern matcher with regex compilation
  - [x] Efficient stack trace traversal

- [x] Log Metadata
  - [x] Add scope identifier to log context
  - [x] Configurable whether to include scope in output
  - [x] Extract caller metadata (file, line, class, function)
  - [x] Relative path formatting
  - [x] Vendor class filtering

- [x] Channel Integration
  - [x] Respect channel-specific configurations
  - [x] Allow per-channel scope enablement
  - [x] Global by default with opt-out
  - [x] Ensure compatibility with all Laravel drivers

- [x] Comprehensive Tests
  - [x] Pattern matching tests (14 tests)
  - [x] Pattern integration tests (7 tests)
  - [x] Metadata tests (6 tests)
  - [x] All existing tests still passing

**Summary**: All 62 tests passing with 105 assertions. Pattern matching with caching, metadata extraction, and full channel integration complete.

## Phase 3: Developer Experience ✅ COMPLETED

**Goal**: Ease of use and debugging

- [x] Artisan Commands
  - [x] `scoped-logger:list` - Show all configured scopes and their levels
  - [x] `scoped-logger:test` - Test what level applies for a given scope/class

- [x] Runtime Modification
  - [x] API to temporarily override scope levels
  - [x] In-memory storage of overrides
  - [x] Reset functionality (clearRuntimeLevel, clearAllRuntimeLevels)
  - [ ] Optional persistence (cache/database) - Deferred to Phase 4

- [x] Scope Filtering
  - [x] Complete suppression of scopes (set to `false`) - Already implemented in Phase 2
  - [ ] Allowlist/blocklist functionality - Deferred to Phase 4

**Summary**: All 82 tests passing with 150 assertions. Artisan commands for list and test. Runtime modification API for temporary scope level overrides. Suppression support via `false` value.

## Phase 4: Advanced Features ✅ COMPLETED

**Goal**: Power user features and production-ready tooling

- [x] Better Error Messages
  - [x] Helpful exceptions with suggestions (InvalidScopeConfigurationException)
  - [x] Context-specific error messages for misconfigurations
  - [x] Debug mode with detailed scope resolution info

- [x] Conditional Logging Rules
  - [x] Closure-based dynamic log levels
  - [x] Environment-based rules (`fn() => app()->environment('local') ? 'debug' : 'error'`)
  - [x] Time-based rules, feature flags, custom logic
  - [x] Runtime evaluation on each log call

- [x] Debug Mode
  - [x] SCOPED_LOG_DEBUG environment variable
  - [x] Detailed resolution info in log context
  - [x] Shows scope, level, resolution method, patterns, overrides

- [x] Documentation & Examples
  - [x] Comprehensive README with all features
  - [x] Usage examples for common patterns
  - [x] Runtime modification examples
  - [x] Conditional logging examples

**Summary**: All 89 tests passing with 158 assertions. Full error handling, conditional logging with closures, debug mode, and comprehensive documentation. Package is production-ready with extensive DX features.

## Phase 5: Advanced Power Features ✅ COMPLETED

**Goal**: Power user features for complex logging scenarios

- [x] Per-Channel Scope Config
  - [x] Different scope levels per channel
  - [x] Channel-specific pattern matching
  - [x] Global scopes as fallback defaults

- [x] Multiple Scopes
  - [x] Support multiple scopes per log entry via `scope()` method with array
  - [x] "Most verbose wins" merging strategy (lowest level)
  - [x] Suppression if any scope is suppressed
  - [x] All scopes included in log context

**Summary**: All 97 tests passing with 163 assertions. Per-channel configurations and multiple scope support. Package is feature-complete with all planned functionality implemented.

## Phase 6: Unknown Scope Detection ✅ COMPLETED

**Goal**: Help catch configuration errors and typos in scope names

- [x] Unknown Scope Handling
  - [x] Detect when scopes are used but not configured
  - [x] Configurable handling: exception, log warning, or ignore
  - [x] Default to throwing exception to catch typos
  - [x] Exception distinguishes single vs multiple unknown scopes
  - [x] Scopes known via: exact match, pattern match, runtime override

- [x] Configuration & Validation
  - [x] `unknown_scope_handling` config option
  - [x] Validate config values in ServiceProvider
  - [x] Environment variable support: `SCOPED_LOG_UNKNOWN_SCOPE`
  - [x] Clear exception messages with suggestions

- [x] Tests & Documentation
  - [x] Test all three handling modes (exception, log, ignore)
  - [x] Test edge cases (pattern matches, runtime overrides, null scopes)
  - [x] Config validation tests
  - [x] Comprehensive README documentation

**Summary**: All 109 tests passing with 186 assertions. Unknown scope detection helps catch configuration typos while remaining flexible for different use cases.

## Future Ideas (Not Yet Implemented)

- [ ] Processor/Middleware Pattern
  - [ ] Hook system for extending functionality
  - [ ] Built-in processors for common use cases

- [ ] Web UI for Runtime Configuration
  - [ ] Visual interface to manage scope levels
  - [ ] Real-time log level adjustments

- [ ] Integration with Laravel Telescope
  - [ ] Show scopes in Telescope log viewer
  - [ ] Filter by scope in Telescope UI

## Design Decisions

### Behavior Clarifications
- When a scope's level is higher than the log call (e.g., scope set to 'error' but code calls `->debug()`), the log is **silently dropped** (not passed to logger)
- Auto-detection supports: property (string), method `getLogScope()`, or closure
- Package is **global by default** with opt-out capability per channel
- Unknown scopes **throw exception by default** to catch configuration typos (configurable: exception, log, or ignore)

### Technical Choices
- Use PSR-3 log level constants for internal comparisons
- Cache scope resolutions per request (not across requests initially)
- Minimal dependency footprint (only what's in composer.json)
