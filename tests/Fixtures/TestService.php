<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Fixtures;

use Tibbs\ScopedLogger\ScopedLogger;
use Tibbs\ScopedLogger\Support\ScopeResolver;

class TestService
{
    public function getResolvedScope(ScopeResolver $resolver): ?string
    {
        return $resolver->resolve();
    }

    public function testPatternMatching(ScopedLogger $logger): void
    {
        $logger->debug('test');
    }
}
