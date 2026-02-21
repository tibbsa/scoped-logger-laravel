<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Commands\TestScopeCommand;

describe('TestScopeCommand Edge Cases', function () {
    it('shows warning when scoped logger is disabled', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'payment'])
            ->expectsOutputToContain('disabled')
            ->assertFailed();
    });

    it('evaluates closure levels for exact scope match', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'payment' => fn () => 'debug',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'payment'])
            ->expectsOutputToContain('debug')
            ->assertSuccessful();
    });

    it('evaluates closure levels for pattern-matched scope', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'App\Services\*' => fn () => 'warning',
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'App\Services\PaymentService'])
            ->expectsOutputToContain('warning')
            ->expectsOutputToContain('Matched Pattern')
            ->assertSuccessful();
    });

    it('handles closure that returns false for suppressed scope', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'noisy' => fn () => false,
            ],
        ]);

        $this->artisan(TestScopeCommand::class, ['scope' => 'noisy'])
            ->expectsOutputToContain('SUPPRESSED')
            ->assertSuccessful();
    });

    it('handles closure that returns non-string value', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'oddscope' => fn () => 42,
            ],
        ]);

        // Non-string closure return becomes null, which means default level is used
        $this->artisan(TestScopeCommand::class, ['scope' => 'oddscope'])
            ->expectsOutputToContain('default')
            ->assertSuccessful();
    });
});
