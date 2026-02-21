<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Commands\ListScopesCommand;

describe('ListScopesCommand Edge Cases', function () {
    it('formats closure levels in scope list', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => fn () => 'debug',
                'auth' => 'error',
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Global Scopes')
            ->expectsOutputToContain('payment')
            ->expectsOutputToContain('auth')
            ->assertSuccessful();
    });

    it('formats closure that returns false as suppressed', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'noisy' => fn () => false,
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('SUPPRESSED')
            ->assertSuccessful();
    });

    it('sorts scopes by level when requested', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
                'auth' => 'error',
                'api' => 'warning',
            ],
        ]);

        $this->artisan(ListScopesCommand::class, ['--sort' => 'level'])
            ->expectsOutputToContain('payment')
            ->expectsOutputToContain('auth')
            ->expectsOutputToContain('api')
            ->assertSuccessful();
    });

    it('skips empty channel scope arrays', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.channel_scopes' => [
                'daily' => [],
                'slack' => ['alert-scope' => 'error'],
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('Channel-Specific Scopes')
            ->expectsOutputToContain('slack')
            ->expectsOutputToContain('alert-scope')
            ->assertSuccessful();
    });

    it('handles closure that returns non-string as info', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'oddscope' => fn () => 42,
            ],
        ]);

        $this->artisan(ListScopesCommand::class)
            ->expectsOutputToContain('info')
            ->assertSuccessful();
    });
});
