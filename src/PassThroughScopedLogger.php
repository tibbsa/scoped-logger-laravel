<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Psr\Log\LoggerInterface;
use Stringable;
use Tibbs\ScopedLogger\Contracts\ScopedLoggerContract;

/**
 * Pass-through wrapper used when scoped logging is disabled (globally via
 * config, or for a specific channel via `disabled_channels`).
 *
 * Accepts the scoped-logger fluent API (scope, setRuntimeLevel, etc.) as
 * silent no-ops so that application code like `Log::scope('x')->info(...)`
 * keeps working without raising errors when the package is turned off.
 * PSR-3 methods delegate directly to the underlying Laravel logger.
 */
class PassThroughScopedLogger implements ScopedLoggerContract
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    /**
     * @param  string|array<int, string>  $scope
     */
    public function scope(string|array $scope): static
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context = []): static
    {
        return $this;
    }

    public function withoutContext(): static
    {
        return $this;
    }

    public function setRuntimeLevel(string $scope, string|false $level): static
    {
        return $this;
    }

    public function clearRuntimeLevel(string $scope): static
    {
        return $this;
    }

    public function clearAllRuntimeLevels(): static
    {
        return $this;
    }

    /**
     * @return array<string, string|false>
     */
    public function getRuntimeLevels(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Forward any other method calls (e.g. Laravel logger extensions) to the
     * underlying logger so channel-specific features keep working.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->logger->$method(...$parameters);
    }
}
