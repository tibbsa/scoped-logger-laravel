<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance\Fixtures;

use Psr\Log\LoggerInterface;

/**
 * Service with static getLogScope() method for auto-detection performance testing
 */
class ServiceWithMethodScope
{
    public static function getLogScope(): string
    {
        return 'perf-method-scope';
    }

    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function logMessage(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}
