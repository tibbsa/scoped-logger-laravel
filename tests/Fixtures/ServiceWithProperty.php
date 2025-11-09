<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Fixtures;

use Tibbs\ScopedLogger\Support\ScopeResolver;

class ServiceWithProperty
{
    protected static string $log_scope = 'custom-payment-scope';

    public function getResolvedScope(ScopeResolver $resolver): ?string
    {
        return $resolver->resolve();
    }
}
