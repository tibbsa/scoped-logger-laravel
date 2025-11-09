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
            ->expectsOutputToContain('Configured Scopes')
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
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('No scopes configured')
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
});
