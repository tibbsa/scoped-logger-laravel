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

    it('tests scope with specific channel', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'slack' => [
                    'payment' => 'error',
                ],
            ],
        ]);

        // Debug: check that Configuration loads channel scopes correctly
        $configArray = config('scoped-logger', []);
        $config = \Tibbs\ScopedLogger\Configuration\Configuration::fromArray($configArray);
        expect($config->scopesForChannel('slack'))->toBe(['payment' => 'error']);

        // Run command and capture output for debugging
        \Illuminate\Support\Facades\Artisan::call('scoped-logger:test', [
            'scope' => 'payment',
            '--channel' => 'slack',
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Debug: check output contains expected text
        expect($output)->toContain('Testing scope');
        expect($output)->toContain('error');
        expect($output)->toContain('channel override');
    });

    it('shows global level when channel has no override', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'slack' => [
                    'api' => 'error',
                ],
            ],
        ]);

        $this->artisan(TestScopeCommand::class, [
            'scope' => 'payment',
            '--channel' => 'slack',
        ])
            ->expectsOutputToContain('debug')
            ->assertSuccessful();
    });

    it('tests scope against all channels', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => [
                    'payment' => 'info',
                ],
                'slack' => [
                    'payment' => 'error',
                ],
            ],
        ]);

        $this->artisan(TestScopeCommand::class, [
            'scope' => 'payment',
            '--all-channels' => true,
        ])
            ->expectsOutputToContain('Global (no channel)')
            ->expectsOutputToContain('Channel: daily')
            ->expectsOutputToContain('Channel: slack')
            ->expectsOutputToContain('Summary')
            ->assertSuccessful();
    });

    it('shows comparison table with all channels', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => ['payment' => 'info'],
                'slack' => ['payment' => 'error'],
            ],
        ]);

        $this->artisan(TestScopeCommand::class, [
            'scope' => 'payment',
            '--level' => 'info',
            '--all-channels' => true,
        ])
            ->expectsOutputToContain('global')
            ->expectsOutputToContain('daily')
            ->expectsOutputToContain('slack')
            ->assertSuccessful();
    });

    it('shows suppressed status for channel-specific suppression', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'verbose' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'slack' => [
                    'verbose' => false,
                ],
            ],
        ]);

        \Illuminate\Support\Facades\Artisan::call('scoped-logger:test', [
            'scope' => 'verbose',
            '--channel' => 'slack',
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        expect($output)->toContain('SUPPRESSED');
        expect($output)->toContain('channel override');
    });

    it('tests pattern match with channel override', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'App\\Services\\*' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'slack' => [
                    'App\\Services\\*' => 'error',
                ],
            ],
        ]);

        \Illuminate\Support\Facades\Artisan::call('scoped-logger:test', [
            'scope' => 'App\\Services\\PaymentService',
            '--channel' => 'slack',
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        expect($output)->toContain('Matched Pattern');
        expect($output)->toContain('error');
        expect($output)->toContain('channel override');
    });
});
