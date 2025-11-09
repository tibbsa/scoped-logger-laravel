<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Support\ScopeResolver;

describe('ScopeResolver', function () {
    it('returns explicit scope when set', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => ['enabled' => true],
        ]);

        $resolver = new ScopeResolver($config);
        $resolver->setExplicitScope('payment');

        expect($resolver->resolve())->toBe('payment');
    });

    it('clears explicit scope', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => ['enabled' => true],
        ]);

        $resolver = new ScopeResolver($config);
        $resolver->setExplicitScope('payment');
        $resolver->clearExplicitScope();

        expect($resolver->resolve())->toBeNull();
    });

    it('returns null when no scope can be resolved', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => ['enabled' => false],
        ]);

        $resolver = new ScopeResolver($config);

        expect($resolver->resolve())->toBeNull();
    });

    it('detects scope from calling class FQCN when configured', function () {
        $config = Configuration::fromArray([
            'scopes' => [
                'Tibbs\\ScopedLogger\\Tests\\Fixtures\\TestService' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\TestService)->getResolvedScope($resolver);

        expect($scope)->toBe('Tibbs\\ScopedLogger\\Tests\\Fixtures\\TestService');
    });

    it('extracts scope from class static property', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => [
                'enabled' => true,
                'property' => 'log_scope',
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\ServiceWithProperty)->getResolvedScope($resolver);

        expect($scope)->toBe('custom-payment-scope');
    });

    it('extracts scope from class static method', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => [
                'enabled' => true,
                'property' => 'getLogScope',
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\ServiceWithMethod)->getResolvedScope($resolver);

        expect($scope)->toBe('method-based-scope');
    });

    it('extracts scope from class static closure', function () {
        $config = Configuration::fromArray([
            'scopes' => [],
            'auto_detection' => [
                'enabled' => true,
                'property' => 'log_scope',
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\ServiceWithClosure)->getResolvedScope($resolver);

        expect($scope)->toBe('closure-scope');
    });

    it('prefers explicit scope over auto-detection', function () {
        $config = Configuration::fromArray([
            'scopes' => [
                'Tibbs\\ScopedLogger\\Tests\\Fixtures\\TestService' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $resolver->setExplicitScope('explicit-scope');

        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\TestService)->getResolvedScope($resolver);

        expect($scope)->toBe('explicit-scope');
    });

    it('prefers class FQCN over class property', function () {
        $config = Configuration::fromArray([
            'scopes' => [
                'Tibbs\\ScopedLogger\\Tests\\Fixtures\\ServiceWithProperty' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'property' => 'log_scope',
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
        ]);

        $resolver = new ScopeResolver($config);
        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\ServiceWithProperty)->getResolvedScope($resolver);

        expect($scope)->toBe('Tibbs\\ScopedLogger\\Tests\\Fixtures\\ServiceWithProperty');
    });
});
