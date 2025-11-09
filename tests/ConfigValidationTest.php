<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\ScopedLoggerServiceProvider;

describe('Configuration Validation', function () {
    it('validates default log level', function () {
        config(['scoped-logger.default_level' => 'invalid']);

        $provider = new ScopedLoggerServiceProvider($this->app);
        $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

        expect(fn () => $provider->packageBooted())
            ->toThrow(
                InvalidArgumentException::class,
                "Invalid log level 'invalid' for config key 'default_level'"
            );
    });

    it('validates scope log levels', function () {
        config([
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'invalid-level',
            ],
        ]);

        $provider = new ScopedLoggerServiceProvider($this->app);
        $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

        expect(fn () => $provider->packageBooted())
            ->toThrow(
                InvalidArgumentException::class,
                "Invalid log level 'invalid-level' for config key 'scopes.payment'"
            );
    });

    it('allows false as a scope level for suppression', function () {
        config([
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => false,
            ],
        ]);

        $provider = new ScopedLoggerServiceProvider($this->app);
        $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

        // Should not throw
        $provider->packageBooted();

        expect(true)->toBeTrue();
    });

    it('accepts all valid PSR-3 log levels', function () {
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        foreach ($validLevels as $level) {
            config([
                'scoped-logger.default_level' => $level,
                'scoped-logger.scopes' => [
                    'test' => $level,
                ],
            ]);

            $provider = new ScopedLoggerServiceProvider($this->app);
            $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

            // Should not throw
            $provider->packageBooted();
        }

        expect(true)->toBeTrue();
    });

    it('validates unknown_scope_handling configuration value', function () {
        config([
            'scoped-logger.default_level' => 'info',
            'scoped-logger.unknown_scope_handling' => 'invalid-value',
        ]);

        $provider = new ScopedLoggerServiceProvider($this->app);
        $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

        expect(fn () => $provider->packageBooted())
            ->toThrow(
                InvalidArgumentException::class,
                "Invalid unknown_scope_handling value 'invalid-value'"
            );
    });

    it('accepts all valid unknown_scope_handling values', function () {
        $validHandling = ['exception', 'log', 'ignore'];

        foreach ($validHandling as $handling) {
            config([
                'scoped-logger.default_level' => 'info',
                'scoped-logger.unknown_scope_handling' => $handling,
            ]);

            $provider = new ScopedLoggerServiceProvider($this->app);
            $provider->configurePackage(new \Spatie\LaravelPackageTools\Package);

            // Should not throw
            $provider->packageBooted();
        }

        expect(true)->toBeTrue();
    });
});
