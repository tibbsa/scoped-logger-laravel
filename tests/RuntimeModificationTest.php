<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Runtime Modification', function () {
    it('allows setting runtime level for a scope', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Normally debug wouldn't log (scope is 'error')
        // But we'll set a runtime override to 'debug'
        $logger->setRuntimeLevel('payment', 'debug');
        $logger->scope('payment')->debug('test');
    });

    it('allows clearing runtime level for a scope', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Set then clear runtime level
        $logger->setRuntimeLevel('payment', 'debug');
        $logger->clearRuntimeLevel('payment');

        // Should use original config (error), so debug won't log
        $logger->scope('payment')->debug('test');
    });

    it('allows clearing all runtime levels', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error',
                'auth' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Set multiple runtime levels
        $logger->setRuntimeLevel('payment', 'debug');
        $logger->setRuntimeLevel('auth', 'debug');

        expect($logger->getRuntimeLevels())->toHaveCount(2);

        // Clear all
        $logger->clearAllRuntimeLevels();

        expect($logger->getRuntimeLevels())->toBeEmpty();
    });

    it('can suppress scope at runtime', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Set runtime level to false (suppressed)
        $logger->setRuntimeLevel('payment', false);

        // Should not log anything
        $logger->scope('payment')->debug('test');
        $logger->scope('payment')->error('test');
    });

    it('validates runtime log level', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect(fn () => $logger->setRuntimeLevel('payment', 'invalid'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('runtime level overrides pattern matches', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Set runtime override for specific scope
        $logger->setRuntimeLevel('App\\Services\\PaymentService', 'debug');

        // Should use runtime level, not pattern match
        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });

    it('returns fluent interface for runtime methods', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'scopes' => [],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect($logger->setRuntimeLevel('payment', 'debug'))->toBe($logger);
        expect($logger->clearRuntimeLevel('payment'))->toBe($logger);
        expect($logger->clearAllRuntimeLevels())->toBe($logger);
    });
});
