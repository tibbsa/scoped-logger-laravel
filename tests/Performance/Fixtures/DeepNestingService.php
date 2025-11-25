<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance\Fixtures;

use Psr\Log\LoggerInterface;

/**
 * Service that simulates deep call stacks for auto-detection performance testing
 */
class DeepNestingService
{
    protected static string $log_scope = 'deep-nesting-scope';

    public function __construct(
        protected LoggerInterface $logger
    ) {}

    /**
     * Create a deeply nested call stack before logging
     */
    public function logWithDepth(string $message, int $depth = 10): void
    {
        $this->recursiveCall($message, $depth);
    }

    protected function recursiveCall(string $message, int $remaining): void
    {
        if ($remaining <= 0) {
            $this->logger->info($message);

            return;
        }

        $this->recursiveCall($message, $remaining - 1);
    }

    /**
     * Create nested call through multiple methods
     */
    public function logWithMultipleMethods(string $message): void
    {
        $this->level1($message);
    }

    protected function level1(string $message): void
    {
        $this->level2($message);
    }

    protected function level2(string $message): void
    {
        $this->level3($message);
    }

    protected function level3(string $message): void
    {
        $this->level4($message);
    }

    protected function level4(string $message): void
    {
        $this->level5($message);
    }

    protected function level5(string $message): void
    {
        $this->logger->info($message);
    }
}
