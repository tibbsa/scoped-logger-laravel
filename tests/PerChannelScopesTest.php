<?php

declare(strict_types=1);

use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Per-Channel Scopes', function () {
    it('uses channel-specific scope configuration', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::any());

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error', // Global: only errors
            ],
            'channel_scopes' => [
                'daily' => [
                    'payment' => 'debug', // Daily channel: verbose
                ],
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config, 'daily');
        $logger->scope('payment')->debug('test');
    });

    it('falls back to global scopes when channel has no override', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error', // Global: only errors
            ],
            'channel_scopes' => [
                'slack' => [
                    // No override for 'payment' scope
                ],
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config, 'slack');
        $logger->scope('payment')->debug('test'); // Should use global 'error' level
    });

    it('channel-specific scope overrides global scope', function () {
        $mockLogger = m::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'api' => 'debug', // Global: verbose
            ],
            'channel_scopes' => [
                'slack' => [
                    'api' => 'error', // Slack: errors only
                ],
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config, 'slack');
        $logger->scope('api')->debug('test'); // Should use slack's 'error' level
    });

    it('works with pattern matching per channel', function () {
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
            'channel_scopes' => [
                'daily' => [
                    'App\\Services\\*' => 'debug',
                ],
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config, 'daily');
        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });
});
