<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Conditional Logging', function () {
    it('allows closure for dynamic log level', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'dynamic' => fn () => 'debug',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('dynamic')->debug('test');
    });

    it('allows environment-based log level', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        App::shouldReceive('environment')
            ->with('local')
            ->andReturn(true);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'conditional' => fn () => App::environment('local') ? 'debug' : 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('conditional')->debug('test');
    });

    it('evaluates closure on each log call', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test1', m::any());

        $counter = 0;
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'dynamic' => function () use (&$counter) {
                    $counter++;

                    return $counter === 1 ? 'debug' : 'error';
                },
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // First call: closure returns 'debug', so debug logs
        $logger->scope('dynamic')->debug('test1');

        // Second call: closure returns 'error', so debug doesn't log
        $logger->scope('dynamic')->debug('test2');

        expect($counter)->toBe(2);
    });

    it('allows closure to return false for suppression', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'suppressed' => fn () => false,
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('suppressed')->debug('test');
        $logger->scope('suppressed')->error('test');
    });

    it('validates closure return value', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'invalid' => fn () => 'invalid-level',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect(fn () => $logger->scope('invalid')->debug('test'))
            ->toThrow(InvalidScopeConfigurationException::class);
    });

    it('works with pattern matching and closures', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => fn () => 'debug',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });

    it('runtime overrides take precedence over closures', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'dynamic' => fn () => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Set runtime override
        $logger->setRuntimeLevel('dynamic', 'debug');

        // Should use runtime level (debug), not closure result (error)
        $logger->scope('dynamic')->debug('test');
    });
});
