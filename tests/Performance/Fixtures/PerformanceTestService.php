<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance\Fixtures;

use Psr\Log\LoggerInterface;

/**
 * Basic service class for performance testing - FQCN as scope
 */
class PerformanceTestService
{
    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function logMessage(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}
