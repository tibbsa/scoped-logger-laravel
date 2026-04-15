<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\PHPStan;

use Illuminate\Support\Facades\Log;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use Tibbs\ScopedLogger\ScopedLogManager;

/**
 * PHPStan extension that teaches static analysis that the Log facade exposes
 * the five ScopedLogManager-specific methods (scope, setRuntimeLevel, etc.)
 * in addition to the standard log methods.
 *
 * Larastan already stubs Illuminate\Support\Facades\Log and
 * Illuminate\Log\LogManager so we cannot add @method annotations via a stub
 * file (phpstan treats duplicate class declarations as a non-ignorable error).
 * Instead we forward method lookups on the Log facade to ScopedLogManager.
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
        return $classReflection->getName() === Log::class
            && in_array($methodName, self::SCOPED_LOGGER_METHODS, true);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->reflectionProvider
            ->getClass(ScopedLogManager::class)
            ->getMethod($methodName, new OutOfClassScope());
    }
}
