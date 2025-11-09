<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Commands\TestScopeCommand;

describe('TestScopeCommand', function () {
    it('tests exact scope match', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'payment'])
            ->expectsOutputToContain('Testing scope')
            ->assertSuccessful();
    });

    it('tests pattern match', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'App\\Services\\*' => 'debug',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'App\\Services\\PaymentService'])
            ->expectsOutputToContain('Testing scope')
            ->expectsOutputToContain('Matched Pattern')
            ->assertSuccessful();
    });

    it('shows default level for unconfigured scope', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'unknown'])
            ->expectsOutputToContain('default')
            ->expectsOutputToContain('info')
            ->assertSuccessful();
    });

    it('shows suppressed status', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'noisy' => false,
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'noisy'])
            ->expectsOutputToContain('SUPPRESSED')
            ->assertSuccessful();
    });

    it('tests custom log level', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'error',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, [
            'scope' => 'payment',
            '--level' => 'debug',
        ])
            ->expectsOutputToContain('WILL BE DROPPED')
            ->assertSuccessful();
    });

    it('shows all log level behaviors', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'test' => 'warning',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'test'])
            ->expectsOutputToContain('Log Level Behavior')
            ->expectsOutputToContain('debug')
            ->expectsOutputToContain('emergency')
            ->assertSuccessful();
    });
});
