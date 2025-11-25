<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance\Fixtures;

use Psr\Log\LoggerInterface;

/**
 * Service with static $log_scope property for auto-detection performance testing
 */
class ServiceWithPropertyScope
{
    protected static string $log_scope = 'perf-property-scope';

    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function logMessage(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
}
