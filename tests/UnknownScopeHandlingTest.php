<?php

declare(strict_types=1);

use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Exceptions\UnknownScopeException;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Unknown Scope Handling', function () {
    it('throws exception for unknown scope when handling is exception', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect(fn () => $logger->scope('unknown-scope')->info('test'))
            ->toThrow(UnknownScopeException::class, "Unknown scope 'unknown-scope' used but not configured");
    });

    it('throws exception for multiple unknown scopes', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect(fn () => $logger->scope(['unknown1', 'unknown2'])->info('test'))
            ->toThrow(UnknownScopeException::class, "Unknown scopes 'unknown1', 'unknown2' used but not configured");
    });

    it('logs warning for unknown scope when handling is log', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        // Expect warning about unknown scope
        $mockLogger->shouldReceive('warning')
            ->once()
            ->with(
                "Unknown scope 'unknown-scope' used but not configured. Using default log level.",
                ['scoped_logger_warning' => 'unknown_scope']
            );

        // Expect the actual log to go through with default level
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'log',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('unknown-scope')->info('test');
    });

    it('silently ignores unknown scope when handling is ignore', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        // Should not log any warning
        $mockLogger->shouldNotReceive('warning');

        // Should log with default level
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'ignore',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('unknown-scope')->info('test');
    });

    it('does not throw exception for known scope', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('payment')->debug('test'); // Should not throw
    });

    it('does not throw exception for pattern-matched scope', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => 'debug',
            ],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope('App\\Services\\PaymentService')->debug('test'); // Should not throw
    });

    it('does not throw exception for scope with runtime override', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->setRuntimeLevel('custom-scope', 'debug');
        $logger->scope('custom-scope')->debug('test'); // Should not throw because of runtime override
    });

    it('throws exception only for unknown scopes in multiple scope call', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // One known, one unknown - should only complain about unknown
        expect(fn () => $logger->scope(['payment', 'unknown-scope'])->info('test'))
            ->toThrow(UnknownScopeException::class, "Unknown scope 'unknown-scope' used but not configured");
    });

    it('does not throw for auto-detected scope when null', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [],
            'unknown_scope_handling' => 'exception',
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->info('test'); // No explicit scope, auto-detection returns null, uses default
    });

    it('defaults to exception handling when config not set', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [],
            // unknown_scope_handling not set, should default to 'exception'
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        expect(fn () => $logger->scope('unknown-scope')->info('test'))
            ->toThrow(UnknownScopeException::class, "Unknown scope 'unknown-scope' used but not configured");
    });
});
