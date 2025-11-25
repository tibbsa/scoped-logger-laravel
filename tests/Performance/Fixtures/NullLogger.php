<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance\Fixtures;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Minimal null logger for performance testing
 *
 * This logger does nothing - it's designed to measure the pure overhead
 * of the scoped logger without any I/O operations.
 */
class NullLogger implements LoggerInterface
{
    protected int $logCount = 0;

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logCount++;
    }

    public function getLogCount(): int
    {
        return $this->logCount;
    }

    public function resetLogCount(): void
    {
        $this->logCount = 0;
    }
}
