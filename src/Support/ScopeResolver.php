<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Support;

use Closure;
use Illuminate\Support\Str;
use Tibbs\ScopedLogger\Configuration\Configuration;

class ScopeResolver
{
    /** @var array<int, string> */
    protected array $explicitScopes = [];

    protected ?PatternMatcher $patternMatcher = null;

    public function __construct(
        protected Configuration $config
    ) {
        // Initialize pattern matcher if patterns exist
        $scopes = $config->scopes();
        if (!empty($scopes)) {
            $this->patternMatcher = new PatternMatcher($scopes);
        }
    }

    /**
     * Set an explicit scope (from the scope() fluent method)
     */
    public function setExplicitScope(?string $scope): void
    {
        $this->explicitScopes = $scope !== null ? [$scope] : [];
    }

    /**
     * Set multiple explicit scopes (from the scope() fluent method with array)
     *
     * @param array<int, string> $scopes
     */
    public function setExplicitScopes(array $scopes): void
    {
        $this->explicitScopes = $scopes;
    }

    /**
     * Clear the explicit scopes
     */
    public function clearExplicitScope(): void
    {
        $this->explicitScopes = [];
    }

    /**
     * Check if an explicit scope is set
     */
    public function hasExplicitScope(): bool
    {
        return !empty($this->explicitScopes);
    }

    /**
     * Get all explicit scopes
     *
     * @return array<int, string>
     */
    public function getExplicitScopes(): array
    {
        return $this->explicitScopes;
    }

    /**
     * Resolve the scope for the current log entry
     * Returns the first scope if multiple are set
     * Hierarchy: explicit → class FQCN → class property/method → default
     */
    public function resolve(): ?string
    {
        // Priority 1: Explicit scope(s) set via scope() method
        if (!empty($this->explicitScopes)) {
            return $this->explicitScopes[0];
        }


        // Priority 2 & 3: Auto-detect from calling class
        if ($this->config->autoDetectionEnabled()) {
            $callingClass = $this->findCallingClass();

            if ($callingClass !== null) {
                // Priority 2: Check if class FQCN is configured as a scope
                if ($this->isScopeConfigured($callingClass)) {
                    return $callingClass;
                }

                // Priority 3: Check for class property/method
                $scopeFromClass = $this->extractScopeFromClass($callingClass);
                if ($scopeFromClass !== null) {
                    return $scopeFromClass;
                }
            }
        }

        // Priority 4: Return null (will use default level)
        return null;
    }

    /**
     * Check if a scope is configured in the config (exact match or pattern)
     */
    protected function isScopeConfigured(string $scope): bool
    {
        // Check exact match first
        $scopes = $this->config->scopes();
        if (isset($scopes[$scope])) {
            return true;
        }

        // Check pattern match
        if ($this->patternMatcher !== null) {
            return $this->patternMatcher->findMatch($scope) !== null;
        }

        return false;
    }

    /**
     * Find the calling class from the stack trace
     */
    protected function findCallingClass(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->config->autoDetectionStackDepth());
//        echo "\nTrace:\n" . print_r($trace, true);

        $skipPaths = $this->config->autoDetectionSkipPaths();

        foreach ($trace as $frame) {
            // Skip frames without a class
            if (!isset($frame['class'])) {
                continue;
            }

            $class = $frame['class'];
            $file = $frame['file'] ?? '';

//            echo "Checking class $class, file $file...\n\n";

            // Skip our own package classes (but not test fixtures)
            if (str_starts_with($class, 'Tibbs\\ScopedLogger\\') &&
                !str_starts_with($class, 'Tibbs\\ScopedLogger\\Tests\\Fixtures\\')) {
//                echo "Skipping scope vendor\n";

                continue;
            }

            // Skip vendor classes if configured
            if ($this->config->autoDetectionSkipVendor() && $this->isVendorClass($class, $file)) {
//                echo "Skipping vednor \n";
                continue;
            }

//            echo "Past vendor check\n";

            // Skip configured paths
            if ($this->shouldSkipPath($file, $skipPaths)) {
                continue;
            }

//            echo "\nFound valid class " . print_r($class, true);

            // Found a valid calling class
            return $class;
        }

        return null;
    }

    /**
     * Extract scope from class property or method
     */
    protected function extractScopeFromClass(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $propertyOrMethod = $this->config->autoDetectionProperty();

        try {
            // Try as a method first (including getters)
            if (method_exists($class, $propertyOrMethod)) {
                $instance = new \ReflectionClass($class);
                $method = $instance->getMethod($propertyOrMethod);

                // Check if method is static
                if ($method->isStatic() && $method->isPublic()) {
                    $result = $class::$propertyOrMethod();
                    return $this->normalizeScopeValue($result);
                }

                // For non-static methods, we'd need an instance
                // For now, skip non-static methods to avoid side effects
            }

            // Try as a property
            if (property_exists($class, $propertyOrMethod)) {
                $instance = new \ReflectionClass($class);
                $property = $instance->getProperty($propertyOrMethod);

                // Only read public or protected static properties
                if ($property->isStatic() && ($property->isPublic() || $property->isProtected())) {
                    $property->setAccessible(true);
                    $result = $property->getValue();
                    return $this->normalizeScopeValue($result);
                }
            }
        } catch (\ReflectionException $e) {
            // Failed to reflect on class, return null
            return null;
        }

        return null;
    }

    /**
     * Normalize the scope value (handle strings, closures, etc.)
     */
    protected function normalizeScopeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Closure) {
            $result = $value();
            return is_string($result) ? $result : null;
        }

        return null;
    }

    /**
     * Check if a class is from a vendor namespace
     * Only checks the class namespace, not file path, because Laravel
     * often calls user code from framework files (e.g., ControllerDispatcher)
     */
    protected function isVendorClass(string $class, string $file): bool
    {
//        echo "isVendorClass - class: $class, file: $file\n";

        // Check if class is from a common vendor namespace
        $vendorNamespaces = [
            'Illuminate\\',
            'Laravel\\',
            'Symfony\\',
            'Composer\\',
            'PHPUnit\\',
            'Mockery\\',
            'Pest\\',
        ];

        foreach ($vendorNamespaces as $namespace) {
            if (str_starts_with($class, $namespace)) {
//                echo "  -> Skipping: class matches vendor namespace {$namespace}\n\n";
                return true;
            }
        }

//        echo "  -> Not vendor class\n\n";
        return false;
    }

    /**
     * Check if a file path should be skipped
     *
     * @param array<int, string> $skipPaths
     */
    protected function shouldSkipPath(string $file, array $skipPaths): bool
    {
        foreach ($skipPaths as $skipPath) {
            if (str_contains($file, $skipPath)) {
                return true;
            }
        }

        return false;
    }
}
