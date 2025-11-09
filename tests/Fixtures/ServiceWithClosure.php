<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Fixtures;

use Closure;
use Tibbs\ScopedLogger\Support\ScopeResolver;

class ServiceWithClosure
{
    protected static Closure $log_scope;

    public function __construct()
    {
        if (!isset(self::$log_scope)) {
            self::$log_scope = fn () => 'closure-scope';
        }
    }

    public function getResolvedScope(ScopeResolver $resolver): ?string
    {
        return $resolver->resolve();
    }
}
