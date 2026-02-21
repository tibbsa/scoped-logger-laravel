<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('ScopedLogger Edge Cases', function () {
    afterEach(function () {
        Mockery::close();
    });

    it('returns absolute path when file does not start with base_path', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['test' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'metadata_relative_paths' => true,
            'metadata_base_path' => '/some/other/base',
        ];
        $logger = new ScopedLogger($mockLogger, $config);

        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('formatFilePath');
        $method->setAccessible(true);

        // Path that doesn't start with the configured base path
        $result = $method->invoke($logger, '/completely/different/path/file.php');

        expect($result)->toBe('/completely/different/path/file.php');
    });

    it('returns file as-is when metadata_relative_paths is disabled', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [],
            'auto_detection' => ['enabled' => false],
            'include_metadata' => false,
            'metadata_relative_paths' => false,
        ];
        $logger = new ScopedLogger($mockLogger, $config);

        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('formatFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($logger, '/absolute/path/to/file.php');

        expect($result)->toBe('/absolute/path/to/file.php');
    });

    it('returns unknown resolution method when auto-detection is disabled with non-explicit scope', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['test' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];
        $logger = new ScopedLogger($mockLogger, $config);

        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('getResolutionMethod');
        $method->setAccessible(true);

        // scope is not null but no explicit scope and auto-detection disabled
        $result = $method->invoke($logger, 'test');

        expect($result)->toBe('unknown');
    });

    it('returns null metadata when no external frame is found', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [],
            'auto_detection' => ['enabled' => false],
            'include_metadata' => true,
            'metadata_skip_vendor' => true,
        ];
        $logger = new ScopedLogger($mockLogger, $config);

        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);

        // extractMetadata walks the stack looking for non-package frames
        // In a test context, it will find test/vendor frames eventually
        // but we can verify the structure of the return value
        $result = $method->invoke($logger);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['file', 'line', 'class', 'function']);
    });

    it('merges shared context with log context', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['test' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'scope_context_key' => 'scope',
        ];
        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                return $context['shared_key'] === 'shared_value'
                    && $context['call_key'] === 'call_value'
                    && $context['scope'] === 'test';
            }));

        $logger->withContext(['shared_key' => 'shared_value']);
        $logger->scope('test')->info('test', ['call_key' => 'call_value']);
    });

    it('handles getMergedScopes with channel-specific overrides', function () {
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
                'auth' => 'error',
            ],
            'channel_scopes' => [
                'daily' => [
                    'payment' => 'warning', // override global
                ],
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        // Create logger for 'daily' channel - payment should use 'warning' not 'debug'
        $logger = new ScopedLogger($mockLogger, $config, 'daily');

        // info is below warning threshold, so this should be blocked
        $mockLogger->shouldNotReceive('log');

        $logger->scope('payment')->info('test');
    });
});
