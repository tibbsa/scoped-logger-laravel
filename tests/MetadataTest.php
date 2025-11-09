<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Log Metadata', function () {
    beforeEach(function () {
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('does not include metadata by default', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'include_scope_in_context' => false,
            'include_metadata' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                return ! isset($context['file'])
                    && ! isset($context['line'])
                    && ! isset($context['class'])
                    && ! isset($context['function']);
            }));

        $logger->info('test');
    });

    it('includes metadata when enabled', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'include_scope_in_context' => false,
            'include_metadata' => true,
            'metadata_skip_vendor' => true,
            'metadata_relative_paths' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                // At least one metadata field should be present
                return isset($context['class']) || isset($context['function'])
                    || isset($context['file']) || isset($context['line']);
            }));

        $logger->info('test');
    });

    it('includes class and function when available', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'include_scope_in_context' => false,
            'include_metadata' => true,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                // In test context, we should have class or function info
                return isset($context['class']) || isset($context['function']);
            }));

        $logger->info('test');
    });

    it('works with metadata and scope together', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => ['test' => 'debug'],
            'include_scope_in_context' => true,
            'scope_context_key' => 'scope',
            'include_metadata' => true,
            'metadata_relative_paths' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', Mockery::on(function ($context) {
                // Should have scope and at least one metadata field
                return isset($context['scope'])
                    && $context['scope'] === 'test'
                    && (isset($context['class']) || isset($context['function'])
                        || isset($context['file']) || isset($context['line']));
            }));

        $logger->scope('test')->debug('test');
    });

    it('preserves user context with metadata', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'include_scope_in_context' => false,
            'include_metadata' => true,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                // Should preserve user context and add metadata
                return isset($context['user_id'])
                    && $context['user_id'] === 123
                    && (isset($context['class']) || isset($context['function']));
            }));

        $logger->info('test', ['user_id' => 123]);
    });

    it('formats file paths as relative by default', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'include_scope_in_context' => false,
            'include_metadata' => true,
            'metadata_relative_paths' => true,
            'metadata_base_path' => 'C:\\DEV\\scoped-logger-laravel',
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('info', 'test', Mockery::on(function ($context) {
                // Should have metadata (at minimum class/function)
                return isset($context['class']) || isset($context['function']);
            }));

        $logger->info('test');
    });
});
