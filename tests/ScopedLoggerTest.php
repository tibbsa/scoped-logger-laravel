<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('ScopedLogger', function () {
    beforeEach(function () {
        // Create a mock logger
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment' => 'debug',
                'auth' => 'error',
                'suppressed' => false,
            ],
            'auto_detection' => [
                'enabled' => false,
            ],
            'include_scope_in_context' => true,
            'scope_context_key' => 'scope',
        ];
    });

    afterEach(function () {
        Mockery::close();
    });

    it('passes through logs when scoped logger is disabled', function () {
        $config = array_merge($this->config, ['enabled' => false]);
        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test message', []);

        $logger->info('test message');
    });

    it('logs when level meets threshold', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test message', []); // No scope key when scope is null

        $logger->info('test message');
    });

    it('blocks logs when level is below threshold', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldNotReceive('log');

        $logger->debug('test message'); // default is 'info', so debug should be blocked
    });

    it('logs debug when scope has debug level', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test message', ['scope' => 'payment']);

        $logger->scope('payment')->debug('test message');
    });

    it('blocks info when scope requires error level', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldNotReceive('log');

        $logger->scope('auth')->info('test message');
    });

    it('logs error when scope requires error level', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('error', 'test message', ['scope' => 'auth']);

        $logger->scope('auth')->error('test message');
    });

    it('suppresses all logs for suppressed scope', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldNotReceive('log');

        $logger->scope('suppressed')->emergency('test message');
    });

    it('suppresses logs at all levels when scope is false', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldNotReceive('log');

        // Test all log levels - all should be suppressed
        $logger->scope('suppressed')->debug('test');
        $logger->scope('suppressed')->info('test');
        $logger->scope('suppressed')->notice('test');
        $logger->scope('suppressed')->warning('test');
        $logger->scope('suppressed')->error('test');
        $logger->scope('suppressed')->critical('test');
        $logger->scope('suppressed')->alert('test');
        $logger->scope('suppressed')->emergency('test');
    });

    it('suppresses logs when using log method directly with suppressed scope', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldNotReceive('log');

        // Use log() method directly with various levels
        $logger->scope('suppressed')->log('debug', 'test');
        $logger->scope('suppressed')->log('info', 'test');
        $logger->scope('suppressed')->log('error', 'test');
        $logger->scope('suppressed')->log('emergency', 'test');
    });

    it('shouldLog returns false when configured level is false', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        // Use reflection to test the protected shouldLog method
        $reflection = new \ReflectionClass($logger);
        $method = $reflection->getMethod('shouldLog');
        $method->setAccessible(true);

        // Test that shouldLog returns false when configuredLevel is false
        $result = $method->invoke($logger, 'emergency', false);
        expect($result)->toBeFalse();

        // Also test with different log levels
        expect($method->invoke($logger, 'debug', false))->toBeFalse();
        expect($method->invoke($logger, 'error', false))->toBeFalse();
    });

    it('clears explicit scope after logging', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'first', ['scope' => 'payment']);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'second', []); // No scope key when scope is null

        $logger->scope('payment')->info('first');
        $logger->info('second'); // Should not have 'payment' scope
    });

    it('adds scope to context when configured', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', ['scope' => 'payment', 'user_id' => 123]);

        $logger->scope('payment')->info('test', ['user_id' => 123]);
    });

    it('does not add scope to context when disabled', function () {
        $config = array_merge($this->config, ['include_scope_in_context' => false]);
        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', ['user_id' => 123]);

        $logger->scope('payment')->info('test', ['user_id' => 123]);
    });

    it('uses custom scope context key', function () {
        $config = array_merge($this->config, ['scope_context_key' => 'log_scope']);
        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', ['log_scope' => 'payment']);

        $logger->scope('payment')->info('test');
    });

    it('supports withContext for shared context', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', ['request_id' => 'abc', 'user_id' => 123]); // No scope key when scope is null

        $logger->withContext(['request_id' => 'abc'])->info('test', ['user_id' => 123]);
    });

    it('clears shared context with withoutContext', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'first', ['request_id' => 'abc']); // No scope key when scope is null

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'second', []); // No scope key when scope is null

        $logger->withContext(['request_id' => 'abc'])->info('first');
        $logger->withoutContext()->info('second');
    });

    it('supports all PSR-3 log levels', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($levels as $level) {
            if ($level === 'debug') {
                // Debug is below default threshold
                $this->mockLogger->shouldNotReceive('log')->with($level, Mockery::any(), Mockery::any());
            } else {
                $this->mockLogger->shouldReceive('log')
                    ->once()
                    ->with($level, "test {$level}", []); // No scope key when scope is null
            }

            $logger->$level("test {$level}");
        }
    });

    it('correctly orders log levels', function () {
        $config = array_merge($this->config, [
            'scopes' => [
                'test' => 'warning',
            ],
        ]);
        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should be blocked (below threshold)
        $this->mockLogger->shouldNotReceive('log')->with('debug', Mockery::any(), Mockery::any());
        $this->mockLogger->shouldNotReceive('log')->with('info', Mockery::any(), Mockery::any());
        $this->mockLogger->shouldNotReceive('log')->with('notice', Mockery::any(), Mockery::any());

        // Should pass (at or above threshold)
        $this->mockLogger->shouldReceive('log')->once()->with('warning', 'w', ['scope' => 'test']);
        $this->mockLogger->shouldReceive('log')->once()->with('error', 'e', ['scope' => 'test']);
        $this->mockLogger->shouldReceive('log')->once()->with('critical', 'c', ['scope' => 'test']);
        $this->mockLogger->shouldReceive('log')->once()->with('alert', 'a', ['scope' => 'test']);
        $this->mockLogger->shouldReceive('log')->once()->with('emergency', 'em', ['scope' => 'test']);

        $logger->scope('test')->debug('d');
        $logger->scope('test')->info('i');
        $logger->scope('test')->notice('n');
        $logger->scope('test')->warning('w');
        $logger->scope('test')->error('e');
        $logger->scope('test')->critical('c');
        $logger->scope('test')->alert('a');
        $logger->scope('test')->emergency('em');
    });

    it('forwards unknown method calls to underlying logger', function () {
        $this->mockLogger->shouldReceive('customMethod')
            ->once()
            ->with('arg1', 'arg2')
            ->andReturn('result');

        $logger = new ScopedLogger($this->mockLogger, $this->config);

        expect($logger->customMethod('arg1', 'arg2'))->toBe('result');
    });

    it('handles Stringable log level', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        // Create a Stringable object that represents a log level
        $stringableLevel = new class implements Stringable {
            public function __toString(): string
            {
                return 'error';
            }
        };

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('error', 'test message', []);

        $logger->log($stringableLevel, 'test message');
    });

    it('falls back to info level for non-string non-Stringable level', function () {
        $logger = new ScopedLogger($this->mockLogger, $this->config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test message', []);

        // Pass an integer as the level (not string, not Stringable)
        $logger->log(123, 'test message');
    });
});
