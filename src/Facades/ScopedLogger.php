<?php

namespace Tibbs\ScopedLogger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Tibbs\ScopedLogger\ScopedLogger
 */
class ScopedLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Tibbs\ScopedLogger\ScopedLogger::class;
    }
}
