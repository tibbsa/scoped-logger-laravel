<?php

namespace TibbsA\ScopedLogger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TibbsA\ScopedLogger\ScopedLogger
 */
class ScopedLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TibbsA\ScopedLogger\ScopedLogger::class;
    }
}
