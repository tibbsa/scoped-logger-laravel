<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\PHPStan;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use Tibbs\ScopedLogger\ScopedLogManager;

/**
 * PHPStan extension that teaches static analysis that the Log facade and the
 * underlying LogManager expose the five ScopedLogManager-specific methods
 * (scope, setRuntimeLevel, etc.) in addition to the standard log methods.
 *
 * Larastan already stubs Illuminate\Support\Facades\Log and
 * Illuminate\Log\LogManager so we cannot add @method annotations via a stub
 * file (phpstan treats duplicate class declarations as a non-ignorable error).
 * Instead we forward method lookups on those classes to ScopedLogManager.
 *
 * The extension matches both Log::class (in case phpstan queries through the
 * facade directly) and LogManager::class (the class larastan resolves the Log
 * facade to after dereferencing — this is the path that actually triggers in
 * practice for chained calls like Log::scope('x')->info('y')).
 */
class LogFacadeExtension implements MethodsClassReflectionExtension
{
    /** @var list<string> */
    private const SCOPED_LOGGER_METHODS = [
        'scope',
        'setRuntimeLevel',
        'clearRuntimeLevel',
        'clearAllRuntimeLevels',
        'getRuntimeLevels',
    ];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (! in_array($methodName, self::SCOPED_LOGGER_METHODS, true)) {
            return false;
        }

        $className = $classReflection->getName();

        return $className === Log::class
            || $className === LogManager::class;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $reflection = $this->reflectionProvider
            ->getClass(ScopedLogManager::class)
            ->getMethod($methodName, new OutOfClassScope());

        return new StaticMethodReflectionDecorator($reflection);
    }
}
