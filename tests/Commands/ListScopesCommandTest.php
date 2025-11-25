<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Commands\ListScopesCommand;

describe('ListScopesCommand', function () {
    it('displays configured scopes', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
                'auth' => 'error',
                'App\\Services\\*' => 'info',
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Global Scopes')
            ->expectsOutputToContain('payment')
            ->expectsOutputToContain('auth')
            ->expectsOutputToContain('App\\Services\\*')
            ->expectsOutputToContain('Default Level')
            ->assertSuccessful();
    });

    it('shows message when no scopes configured', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [],
            'scoped-logger.channel_scopes' => [],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('No global scopes configured')
            ->assertSuccessful();
    });

    it('warns when scoped logger is disabled', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('disabled')
            ->assertFailed();
    });

    it('identifies pattern scopes', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
                'App\\*' => 'info',
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Is Pattern')
            ->assertSuccessful();
    });

    it('shows suppressed scopes', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'noisy' => false,
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('SUPPRESSED')
            ->assertSuccessful();
    });

    it('displays channel-specific scopes', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => [
                    'payment' => 'info',
                    'api' => 'debug',
                ],
                'slack' => [
                    'payment' => 'error',
                ],
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Global Scopes')
            ->expectsOutputToContain('Channel-Specific Scopes')
            ->expectsOutputToContain('daily')
            ->expectsOutputToContain('slack')
            ->expectsOutputToContain('api')
            ->assertSuccessful();
    });

    it('displays effective scopes for a specific channel', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
                'auth' => 'warning',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => [
                    'payment' => 'info',
                    'api' => 'debug',
                ],
            ],
        ]);

        $this->artisan(ListScopesCommand::class, ['--channel' => 'daily'])
            ->expectsOutputToContain('Effective Scopes for Channel: daily')
            ->expectsOutputToContain('payment')
            ->expectsOutputToContain('auth')
            ->expectsOutputToContain('api')
            ->expectsOutputToContain('Source')
            ->expectsOutputToContain('Channel Overrides')
            ->assertSuccessful();
    });

    it('shows only global scopes when channel has no overrides', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [],
        ]);

        $this->artisan(ListScopesCommand::class, ['--channel' => 'daily'])
            ->expectsOutputToContain('Effective Scopes for Channel: daily')
            ->expectsOutputToContain('payment')
            ->assertSuccessful();
    });

    it('handles channel with no scopes when global scopes are also empty', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'warning',
            'scoped-logger.scopes' => [],
            'scoped-logger.channel_scopes' => [],
        ]);

        $this->artisan(ListScopesCommand::class, ['--channel' => 'nonexistent'])
            ->expectsOutputToContain('No scopes configured for this channel')
            ->expectsOutputToContain('Default Level')
            ->assertSuccessful();
    });

    it('displays suppressed scopes in channel configuration', function () {
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

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Channel-Specific Scopes')
            ->expectsOutputToContain('slack')
            ->expectsOutputToContain('SUPPRESSED')
            ->assertSuccessful();
    });

    it('shows total channels with overrides count', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => ['payment' => 'info'],
                'slack' => ['payment' => 'error'],
                'sentry' => ['payment' => 'critical'],
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Total Channels with Overrides')
            ->assertSuccessful();
    });
});
