<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/**
 * Wraps a MethodReflection and overrides isStatic() to return true.
 *
 * When phpstan resolves a Log facade call (e.g. Log::scope()), the call site is
 * a static call. Our LogFacadeExtension returns a reflection for a ScopedLogManager
 * instance method (isStatic() === false). PHPStan then raises method.staticCall
 * because the reflection disagrees with the call site.
 *
 * This decorator fixes the mismatch by reporting the method as static while
 * delegating all other interface methods to the wrapped reflection unchanged.
 *
 * @internal
 */
final class StaticMethodReflectionDecorator implements MethodReflection
{
    public function __construct(private readonly MethodReflection $wrapped)
    {
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->wrapped->getDeclaringClass();
    }

    /**
     * Always returns true so phpstan accepts this reflection at a static call site
     * (e.g. Log::scope(...)) without raising method.staticCall.
     */
    public function isStatic(): bool
    {
        return true;
    }

    public function isPrivate(): bool
    {
        return $this->wrapped->isPrivate();
    }

    public function isPublic(): bool
    {
        return $this->wrapped->isPublic();
    }

    public function getDocComment(): ?string
    {
        return $this->wrapped->getDocComment();
    }

    public function getName(): string
    {
        return $this->wrapped->getName();
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this->wrapped->getPrototype();
    }

    /** @return list<\PHPStan\Reflection\ParametersAcceptor> */
    public function getVariants(): array
    {
        return $this->wrapped->getVariants();
    }

    public function isDeprecated(): TrinaryLogic
    {
        return $this->wrapped->isDeprecated();
    }

    public function getDeprecatedDescription(): ?string
    {
        return $this->wrapped->getDeprecatedDescription();
    }

    public function isFinal(): TrinaryLogic
    {
        return $this->wrapped->isFinal();
    }

    public function isInternal(): TrinaryLogic
    {
        return $this->wrapped->isInternal();
    }

    public function getThrowType(): ?Type
    {
        return $this->wrapped->getThrowType();
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return $this->wrapped->hasSideEffects();
    }
}
