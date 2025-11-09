<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Fixtures;

use Tibbs\ScopedLogger\Support\ScopeResolver;

class ServiceWithMethod
{
    public static function getLogScope(): string
    {
        return 'method-based-scope';
    }

    public function getResolvedScope(ScopeResolver $resolver): ?string
    {
        return $resolver->resolve();
    }
}
