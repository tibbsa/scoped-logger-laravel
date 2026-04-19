<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Contracts;

use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\PassThroughScopedLogger;
use Tibbs\ScopedLogger\ScopedLogger;

/**
 * Contract implemented by any logger exposing the scoped-logger fluent API.
 *
 * Implemented by {@see ScopedLogger} (active) and
 * {@see PassThroughScopedLogger} (no-op fallback used
 * when scoped logging is disabled globally or for a specific channel).
 */
interface ScopedLoggerContract extends LoggerInterface
{
    /**
     * @param  string|array<int, string>  $scope
     */
    public function scope(string|array $scope): static;

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context = []): static;

    public function withoutContext(): static;

    public function setRuntimeLevel(string $scope, string|false $level): static;

    public function clearRuntimeLevel(string $scope): static;

    public function clearAllRuntimeLevels(): static;

    /**
     * @return array<string, string|false>
     */
    public function getRuntimeLevels(): array;
}
