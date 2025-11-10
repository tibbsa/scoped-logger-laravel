<?php

declare(strict_types=1);

use Mockery as m;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Debug Mode', function () {
    afterEach(function () {
        m::close();
    });

    it('adds debug information to context when debug mode is enabled', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'debug_mode' => true,
            'include_scope_in_context' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test message', m::on(function ($context) {
                // Verify scope is present
                expect($context)->toHaveKey('scope');
                expect($context['scope'])->toBe('payment');

                // Verify debug info is present
                expect($context)->toHaveKey('scoped_logger_debug');
                expect($context['scoped_logger_debug'])->toHaveKey('resolved_scope');
                expect($context['scoped_logger_debug']['resolved_scope'])->toBe('payment');
                expect($context['scoped_logger_debug'])->toHaveKey('log_level');
                expect($context['scoped_logger_debug']['log_level'])->toBe('debug');
                expect($context['scoped_logger_debug'])->toHaveKey('configured_level');
                expect($context['scoped_logger_debug']['configured_level'])->toBe('debug');
                expect($context['scoped_logger_debug'])->toHaveKey('resolution_method');
                expect($context['scoped_logger_debug']['resolution_method'])->toBe('explicit (scope() method)');
                expect($context['scoped_logger_debug'])->toHaveKey('runtime_override');
                expect($context['scoped_logger_debug']['runtime_override'])->toBe('no');

                return true;
            }));

        $logger->scope('payment')->debug('test message');
    });

    it('does not add debug information when debug mode is disabled', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
            ],
            'debug_mode' => false,
            'include_scope_in_context' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test message', m::on(function ($context) {
                // Verify scope is present but debug info is not
                expect($context)->toHaveKey('scope');
                expect($context)->not->toHaveKey('scoped_logger_debug');

                return true;
            }));

        $logger->scope('payment')->debug('test message');
    });

    it('shows SUPPRESSED for suppressed scope in debug mode', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        // Create a logger with a suppressed scope but temporarily enabled for this test
        // We'll use reflection to check what would be added if it weren't suppressed
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'test' => 'info',
            ],
            'debug_mode' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        // Use reflection to test addDebugInfoToContext directly
        $reflection = new \ReflectionClass($logger);
        $method = $reflection->getMethod('addDebugInfoToContext');
        $method->setAccessible(true);

        $context = $method->invoke($logger, [], 'test', 'debug', false);

        expect($context)->toHaveKey('scoped_logger_debug');
        expect($context['scoped_logger_debug']['configured_level'])->toBe('SUPPRESSED');
    });

    it('shows runtime override in debug information', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'error',
            ],
            'debug_mode' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);
        $logger->setRuntimeLevel('payment', 'debug');

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::on(function ($context) {
                expect($context['scoped_logger_debug']['runtime_override'])->toBe('yes');

                return true;
            }));

        $logger->scope('payment')->debug('test');
    });

    it('includes matched pattern in debug information', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => 'debug',
            ],
            'debug_mode' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::on(function ($context) {
                expect($context)->toHaveKey('scoped_logger_debug');
                expect($context['scoped_logger_debug'])->toHaveKey('matched_pattern');
                expect($context['scoped_logger_debug']['matched_pattern'])->toBe('App\\Services\\*');

                return true;
            }));

        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });

    it('does not include matched pattern when scope matches exactly', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
                'pay*' => 'info',
            ],
            'debug_mode' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', m::on(function ($context) {
                expect($context)->toHaveKey('scoped_logger_debug');
                // matched_pattern should not be present for exact matches
                expect($context['scoped_logger_debug'])->not->toHaveKey('matched_pattern');

                return true;
            }));

        $logger->scope('payment')->debug('test');
    });

    it('shows default resolution method when no scope detected', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [],
            'debug_mode' => true,
            'auto_detection' => [
                'enabled' => false,
            ],
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', m::on(function ($context) {
                expect($context['scoped_logger_debug']['resolved_scope'])->toBe('(no scope)');
                expect($context['scoped_logger_debug']['resolution_method'])->toBe('default (no scope detected)');

                return true;
            }));

        $logger->info('test');
    });

    it('includes all debug fields for various log levels', function () {
        $mockLogger = m::mock(LoggerInterface::class);

        $config = [
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'test' => 'debug',
            ],
            'debug_mode' => true,
        ];

        $logger = new ScopedLogger($mockLogger, $config);

        $levels = ['debug', 'info', 'warning', 'error', 'emergency'];

        foreach ($levels as $level) {
            $mockLogger->shouldReceive('log')
                ->once()
                ->with($level, "test {$level}", m::on(function ($context) use ($level) {
                    expect($context['scoped_logger_debug']['log_level'])->toBe($level);
                    expect($context['scoped_logger_debug']['configured_level'])->toBe('debug');

                    return true;
                }));

            $logger->scope('test')->$level("test {$level}");
        }
    });
});
