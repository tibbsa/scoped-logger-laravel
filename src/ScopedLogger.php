<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Closure;
use Psr\Log\LoggerInterface;
use Stringable;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;
use Tibbs\ScopedLogger\Exceptions\UnknownScopeException;
use Tibbs\ScopedLogger\Support\PatternMatcher;
use Tibbs\ScopedLogger\Support\ScopeResolver;

class ScopedLogger implements LoggerInterface
{
    protected ScopeResolver $scopeResolver;

    /** @var array<string, mixed> */
    protected array $sharedContext = [];

    protected ?PatternMatcher $patternMatcher = null;

    /** @var array<string, string|false> */
    protected array $runtimeLevels = [];

    protected string $channelName;

    protected Configuration $config;

    /**
     * @param array<string, mixed>|Configuration $config
     */
    public function __construct(
        protected LoggerInterface $logger,
        Configuration|array $config,
        string $channelName = 'default'
    ) {
        $this->config = $config instanceof Configuration
            ? $config
            : Configuration::fromArray($config);
        $this->channelName = $channelName;
        $this->scopeResolver = new ScopeResolver($this->config);

        // Initialize pattern matcher with merged scopes (channel-specific + global)
        $mergedScopes = $this->getMergedScopes();
        if (!empty($mergedScopes)) {
            $this->patternMatcher = new PatternMatcher($mergedScopes);
        }
    }

    /**
     * Get merged scopes (channel-specific overrides global)
     *
     * @return array<string, string|false|\Closure>
     */
    protected function getMergedScopes(): array
    {
        $globalScopes = $this->config->scopes();
        $channelScopes = $this->config->scopesForChannel($this->channelName);

        // Channel-specific scopes override global scopes
        return array_merge($globalScopes, $channelScopes);
    }

    /**
     * Set the scope(s) for the next log entry (fluent method)
     *
     * Accepts either a single scope string or an array of scopes.
     * When multiple scopes are provided, uses "most verbose wins" strategy
     * (the lowest log level among all scopes).
     *
     * @param string|array<int, string> $scope
     */
    public function scope(string|array $scope): static
    {
        if (is_array($scope)) {
            $this->scopeResolver->setExplicitScopes($scope);
        } else {
            $this->scopeResolver->setExplicitScope($scope);
        }

        return $this;
    }

    /**
     * Add context to share with all logs
     *
     * @param array<string, mixed> $context
     */
    public function withContext(array $context = []): static
    {
        $this->sharedContext = array_merge($this->sharedContext, $context);

        return $this;
    }

    /**
     * Flush the shared context
     */
    public function withoutContext(): static
    {
        $this->sharedContext = [];

        return $this;
    }

    /**
     * Set a runtime override for a scope's log level
     * This temporarily overrides the configured level until cleared
     */
    public function setRuntimeLevel(string $scope, string|false $level): static
    {
        if (empty($scope)) {
            throw InvalidScopeConfigurationException::emptyScope();
        }

        if ($level !== false) {
            $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
            if (!in_array($level, $validLevels)) {
                throw InvalidScopeConfigurationException::invalidLevel($level, "runtime.{$scope}");
            }
        }

        $this->runtimeLevels[$scope] = $level;

        return $this;
    }

    /**
     * Clear runtime override for a specific scope
     */
    public function clearRuntimeLevel(string $scope): static
    {
        unset($this->runtimeLevels[$scope]);

        return $this;
    }

    /**
     * Clear all runtime level overrides
     */
    public function clearAllRuntimeLevels(): static
    {
        $this->runtimeLevels = [];

        return $this;
    }

    /**
     * Get all runtime level overrides
     *
     * @return array<string, string|false>
     */
    public function getRuntimeLevels(): array
    {
        return $this->runtimeLevels;
    }

    /**
     * System is unusable.
     *
     * @param array<string, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param array<string, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param array<string, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param array<string, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param array<string, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param array<string, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // PSR-3 allows mixed for level, but we need string
        // Handle both string and Stringable types
        if (is_string($level)) {
            $levelString = $level;
        } elseif ($level instanceof Stringable) {
            $levelString = (string) $level;
        } else {
            $levelString = 'info'; // Fallback to info for any other type
        }

        // Check if scoped logger is enabled
        if (!$this->config->isEnabled()) {
            $this->logger->log($levelString, $message, $this->mergeContext($context));
            $this->scopeResolver->clearExplicitScope();

            return;
        }

        // Get all scopes (single or multiple)
        $explicitScopes = $this->scopeResolver->getExplicitScopes();
        $scope = empty($explicitScopes) ? $this->scopeResolver->resolve() : null;

        // Handle unknown scopes based on configuration
        if (!empty($explicitScopes)) {
            $this->handleUnknownScopes($explicitScopes, $levelString, $message, $context);
        } elseif ($scope !== null) {
            $this->handleUnknownScopes([$scope], $levelString, $message, $context);
        }

        // Determine the effective configured level
        if (!empty($explicitScopes)) {
            // Multiple scopes: use most verbose (lowest) level
            $configuredLevel = $this->getMostVerboseLevel($explicitScopes);
            $scope = implode(', ', $explicitScopes); // For context
        } else {
            // Single scope or auto-detected
            $configuredLevel = $this->getConfiguredLevel($scope);
        }

        // Check if this log should be suppressed completely
        if ($configuredLevel === false) {
            $this->scopeResolver->clearExplicitScope();

            return;
        }

        // Check if this log level meets the threshold
        if (!$this->shouldLog($levelString, $configuredLevel)) {
            $this->scopeResolver->clearExplicitScope();

            return;
        }

        // Add scope to context if configured
        $context = $this->addScopeToContext($context, $scope);

        // Add metadata to context if configured
        $context = $this->addMetadataToContext($context);

        // Add debug information if debug mode is enabled
        $context = $this->addDebugInfoToContext($context, $scope, $levelString, $configuredLevel);

        // Pass the log to the underlying logger
        $this->logger->log($levelString, $message, $this->mergeContext($context));

        // Clear explicit scope after logging
        $this->scopeResolver->clearExplicitScope();
    }

    /**
     * Get the configured log level for a scope
     * Returns false if scope is suppressed, string level if configured, or default level
     */
    protected function getConfiguredLevel(?string $scope): string|false
    {
        if ($scope === null) {
            return $this->config->defaultLevel();
        }

        // Check runtime overrides first
        if (isset($this->runtimeLevels[$scope])) {
            return $this->runtimeLevels[$scope];
        }

        // Get merged scopes (channel-specific + global)
        $mergedScopes = $this->getMergedScopes();

        // Try exact match in merged scopes
        if (isset($mergedScopes[$scope])) {
            return $this->resolveLevel($mergedScopes[$scope]);
        }

        // Try pattern matching if available
        if ($this->patternMatcher !== null) {
            $matchedPattern = $this->patternMatcher->findMatch($scope);
            if ($matchedPattern !== null) {
                // Check if there's a runtime override for the matched pattern
                if (isset($this->runtimeLevels[$matchedPattern])) {
                    return $this->runtimeLevels[$matchedPattern];
                }

                return $this->resolveLevel($mergedScopes[$matchedPattern]);
            }
        }

        // Return default level
        return $this->config->defaultLevel();
    }

    /**
     * Get the most verbose (lowest) log level among multiple scopes
     * Returns false if any scope is suppressed
     *
     * @param array<int, string> $scopes
     */
    protected function getMostVerboseLevel(array $scopes): string|false
    {
        $levels = [
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7,
        ];

        $mostVerboseLevel = null;
        $mostVerboseValue = PHP_INT_MAX;

        foreach ($scopes as $scope) {
            $configuredLevel = $this->getConfiguredLevel($scope);

            // If any scope is suppressed, suppress the entire log
            if ($configuredLevel === false) {
                return false;
            }

            $levelValue = $levels[$configuredLevel] ?? PHP_INT_MAX;

            // Lower value = more verbose
            if ($levelValue < $mostVerboseValue) {
                $mostVerboseValue = $levelValue;
                $mostVerboseLevel = $configuredLevel;
            }
        }

        return $mostVerboseLevel ?? $this->config->defaultLevel();
    }

    /**
     * Resolve a level value (handles closures for conditional logic)
     */
    protected function resolveLevel(string|false|Closure $level): string|false
    {
        if ($level instanceof Closure) {
            $resolved = $level();

            // Validate the resolved level
            if ($resolved !== false && !in_array($resolved, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])) {
                $resolvedStr = is_string($resolved) ? $resolved : gettype($resolved);
                throw InvalidScopeConfigurationException::invalidLevel($resolvedStr, 'closure');
            }

            return is_string($resolved) || $resolved === false ? $resolved : false;
        }

        return $level;
    }

    /**
     * Check if a log at the given level should be logged based on the configured level
     */
    protected function shouldLog(string $logLevel, string|false $configuredLevel): bool
    {
        if ($configuredLevel === false) {
            return false;
        }

        $levels = [
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7,
        ];

        $logLevelValue = $levels[$logLevel] ?? 0;
        $configuredLevelValue = $levels[$configuredLevel] ?? 1;

        return $logLevelValue >= $configuredLevelValue;
    }

    /**
     * Add scope to context if configured
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function addScopeToContext(array $context, ?string $scope): array
    {
        // Don't add scope if it's null
        if ($scope === null) {
            return $context;
        }

        // Don't add scope if disabled in config
        if (!$this->config->includeScopeInContext()) {
            return $context;
        }

        $key = $this->config->scopeContextKey();
        $context[$key] = $scope;

        return $context;
    }

    /**
     * Add metadata to context if configured
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function addMetadataToContext(array $context): array
    {
        if (!$this->config->includeMetadata()) {
            return $context;
        }

        $metadata = $this->extractMetadata();

        foreach ($metadata as $key => $value) {
            if ($value !== null) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * Extract metadata from the current call stack
     *
     * @return array{file: string|null, line: int|null, class: string|null, function: string|null}
     */
    protected function extractMetadata(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        // Find the first frame outside of this package
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            $class = $frame['class'] ?? null;

            // Skip our own classes
            if ($class && str_starts_with($class, 'TibbsA\\ScopedLogger\\')) {
                continue;
            }

            // Skip Illuminate\Log classes
            if ($class && str_starts_with($class, 'Illuminate\\Log\\')) {
                continue;
            }

            // Skip vendor path if configured
            if ($file && (str_contains($file, '/vendor/') || str_contains($file, '\\vendor\\'))) {
                if ($this->config->metadataSkipVendor()) {
                    continue;
                }
            }

            // Found the calling location
            return [
                'file' => isset($frame['file']) ? $this->formatFilePath($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'],
            ];
        }

        return [
            'file' => null,
            'line' => null,
            'class' => null,
            'function' => null,
        ];
    }

    /**
     * Format file path (optionally make it relative to base path)
     */
    protected function formatFilePath(string $file): string
    {
        if (!$this->config->metadataRelativePaths()) {
            return $file;
        }

        $basePath = $this->config->metadataBasePath() ?? base_path();

        if (str_starts_with($file, $basePath)) {
            return substr($file, strlen($basePath) + 1);
        }

        return $file;
    }

    /**
     * Add debug information to context if debug mode is enabled
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function addDebugInfoToContext(array $context, ?string $scope, string $logLevel, string|false $configuredLevel): array
    {
        if (!$this->config->debugMode()) {
            return $context;
        }

        $debug = [
            'scoped_logger_debug' => [
                'resolved_scope' => $scope ?? '(no scope)',
                'log_level' => $logLevel,
                'configured_level' => $configuredLevel === false ? 'SUPPRESSED' : $configuredLevel,
                'resolution_method' => $this->getResolutionMethod($scope),
                'runtime_override' => isset($this->runtimeLevels[$scope ?? '']) ? 'yes' : 'no',
            ],
        ];

        // Add pattern match info if applicable
        if ($scope && $this->patternMatcher !== null) {
            $matchedPattern = $this->patternMatcher->findMatch($scope);
            if ($matchedPattern !== null && $matchedPattern !== $scope) {
                $debug['scoped_logger_debug']['matched_pattern'] = $matchedPattern;
            }
        }

        return array_merge($context, $debug);
    }

    /**
     * Get the method used to resolve the scope
     */
    protected function getResolutionMethod(?string $scope): string
    {
        if ($scope === null) {
            return 'default (no scope detected)';
        }

        if ($this->scopeResolver->hasExplicitScope()) {
            return 'explicit (scope() method)';
        }

        // Check if it's from auto-detection
        if ($this->config->autoDetectionEnabled()) {
            return 'auto-detected (class FQCN or property)';
        }

        return 'unknown';
    }

    /**
     * Merge shared context with provided context
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function mergeContext(array $context): array
    {
        return array_merge($this->sharedContext, $context);
    }

    /**
     * Check if a scope is known (configured or matches a pattern)
     */
    protected function isScopeKnown(string $scope): bool
    {
        // Check if scope has a runtime override
        if (isset($this->runtimeLevels[$scope])) {
            return true;
        }

        // Get merged scopes (channel-specific + global)
        $mergedScopes = $this->getMergedScopes();

        // Check for exact match
        if (isset($mergedScopes[$scope])) {
            return true;
        }

        // Check for pattern match
        if ($this->patternMatcher !== null) {
            $matchedPattern = $this->patternMatcher->findMatch($scope);
            if ($matchedPattern !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle unknown scopes based on configuration
     *
     * @param array<int, string> $scopes
     * @param array<string, mixed> $context
     */
    protected function handleUnknownScopes(array $scopes, string $level, string|Stringable $message, array $context): void
    {
        // Filter to only unknown scopes and re-index array
        $unknownScopes = array_values(array_filter($scopes, fn ($scope) => !$this->isScopeKnown($scope)));

        if (empty($unknownScopes)) {
            return;
        }

        $handling = $this->config->unknownScopeHandling();

        switch ($handling) {
            case 'exception':
                if (count($unknownScopes) === 1) {
                    throw UnknownScopeException::forScope($unknownScopes[0]);
                }
                throw UnknownScopeException::forScopes($unknownScopes);

            case 'log':
                // Log a warning using the underlying logger
                $scopeList = implode("', '", $unknownScopes);
                $count = count($unknownScopes);
                $plural = $count > 1 ? 's' : '';
                $this->logger->warning(
                    "Unknown scope{$plural} '{$scopeList}' used but not configured. Using default log level.",
                    ['scoped_logger_warning' => 'unknown_scope']
                );
                break;

            case 'ignore':
                // Silently ignore - do nothing
                break;
        }
    }

    /**
     * Forward method calls to the underlying logger
     * This ensures compatibility with Laravel's logger extensions
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->logger->$method(...$parameters);
    }
}
