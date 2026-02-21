<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Support\ScopeResolver;

describe('ScopeResolver Edge Cases', function () {
    it('returns null when class does not exist', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => ['enabled' => true],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('extractScopeFromClass');
        $method->setAccessible(true);

        $result = $method->invoke($resolver, 'NonExistent\\Class\\Name');

        expect($result)->toBeNull();
    });

    it('normalizes null scope value to null', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('normalizeScopeValue');
        $method->setAccessible(true);

        expect($method->invoke($resolver, null))->toBeNull();
    });

    it('normalizes non-string non-closure value to null', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('normalizeScopeValue');
        $method->setAccessible(true);

        expect($method->invoke($resolver, 123))->toBeNull()
            ->and($method->invoke($resolver, true))->toBeNull()
            ->and($method->invoke($resolver, []))->toBeNull();
    });

    it('normalizes closure scope value', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('normalizeScopeValue');
        $method->setAccessible(true);

        $closure = fn () => 'my-scope';
        expect($method->invoke($resolver, $closure))->toBe('my-scope');

        $nullClosure = fn () => null;
        expect($method->invoke($resolver, $nullClosure))->toBeNull();

        $intClosure = fn () => 42;
        expect($method->invoke($resolver, $intClosure))->toBeNull();
    });

    it('identifies vendor namespace classes', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => ['enabled' => true, 'skip_vendor' => true],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('isVendorClass');
        $method->setAccessible(true);

        expect($method->invoke($resolver, 'Illuminate\\Support\\Str', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'Laravel\\Sanctum\\Guard', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'Symfony\\Component\\HttpFoundation\\Request', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'Composer\\Autoload\\ClassLoader', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'PHPUnit\\Framework\\TestCase', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'Mockery\\MockInterface', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'Pest\\TestSuite', ''))->toBeTrue()
            ->and($method->invoke($resolver, 'App\\Services\\PaymentService', ''))->toBeFalse();
    });

    it('checks skip paths', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldSkipPath');
        $method->setAccessible(true);

        expect($method->invoke($resolver, '/app/vendor/laravel/framework/src/File.php', ['/vendor/']))
            ->toBeTrue()
            ->and($method->invoke($resolver, '/app/bootstrap/cache/packages.php', ['/bootstrap/']))
            ->toBeTrue()
            ->and($method->invoke($resolver, '/app/src/Services/Payment.php', ['/vendor/', '/bootstrap/']))
            ->toBeFalse();
    });

    it('returns null when no valid calling class is found in stack', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 30,
                'skip_vendor' => true,
            ],
        ]);
        $resolver = new ScopeResolver($config);

        // When resolve() is called from test code, the stack frames will
        // all be from vendor/test classes which get skipped
        // The resolver should gracefully return null
        $result = $resolver->resolve();

        expect($result)->toBeNull();
    });

    it('exercises full stack traversal with vendor skipping via resolve', function () {
        // With a large stack depth, resolve() traverses more frames,
        // exercising vendor class and frameless entry skipping in findCallingClass
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 30,
                'skip_vendor' => true,
                'skip_paths' => ['/some/test/path/'],
            ],
        ]);
        $resolver = new ScopeResolver($config);

        // All frames in the stack are package/vendor/test classes,
        // so auto-detection finds nothing and resolve returns null
        $result = $resolver->resolve();

        expect($result)->toBeNull();
    });

    it('sets and gets explicit scopes', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        expect($resolver->hasExplicitScope())->toBeFalse()
            ->and($resolver->getExplicitScopes())->toBe([]);

        $resolver->setExplicitScopes(['payment', 'api']);

        expect($resolver->hasExplicitScope())->toBeTrue()
            ->and($resolver->getExplicitScopes())->toBe(['payment', 'api']);

        $resolver->clearExplicitScope();

        expect($resolver->hasExplicitScope())->toBeFalse()
            ->and($resolver->getExplicitScopes())->toBe([]);
    });

    it('sets explicit scope to empty when null is passed', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
        ]);
        $resolver = new ScopeResolver($config);

        $resolver->setExplicitScope('payment');
        expect($resolver->hasExplicitScope())->toBeTrue();

        $resolver->setExplicitScope(null);
        expect($resolver->hasExplicitScope())->toBeFalse()
            ->and($resolver->getExplicitScopes())->toBe([]);
    });
});
