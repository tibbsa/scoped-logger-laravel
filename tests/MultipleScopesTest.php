<?php

declare(strict_types=1);

use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Multiple Scopes', function () {
    it('uses most verbose level among multiple scopes', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',  // Most verbose
                'api' => 'error',      // Least verbose
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['payment', 'api'])->debug('test'); // Should use 'debug' from payment
    });

    it('suppresses log if any scope is suppressed', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
                'noisy' => false, // Suppressed
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['payment', 'noisy'])->debug('test'); // Should be suppressed
    });

    it('includes all scopes in context', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::on(function ($context) {
                return isset($context['scope']) && $context['scope'] === 'payment, api';
            }));

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'info',
                'api' => 'info',
            ],
            'include_scope_in_context' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['payment', 'api'])->info('test');
    });

    it('uses most verbose among mixed levels', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'auth' => 'error',
                'api' => 'info',      // Most verbose
                'payment' => 'warning',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['auth', 'api', 'payment'])->info('test'); // Should use 'info'
    });

    it('blocks log if level does not meet any scope threshold', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'warning',
                'api' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['payment', 'api'])->debug('test'); // Both require higher than debug
    });

    it('works with pattern-matched scopes', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => 'debug',
                'api' => 'error',
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->scope(['App\\Services\\PaymentService', 'api'])->debug('test'); // Should use 'debug' from pattern
    });
});
