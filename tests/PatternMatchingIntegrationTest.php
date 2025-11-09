<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\ScopedLogger;

describe('Pattern Matching Integration', function () {
    beforeEach(function () {
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('filters logs using wildcard patterns', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\Services\\*' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should log because pattern matches and allows debug
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', []);

        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });

    it('uses most specific pattern match', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\*' => 'warning',
                'App\\Services\\*' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should use App\\Services\\* pattern (more specific)
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', []);

        $logger->scope('App\\Services\\PaymentService')->debug('test');
    });

    it('falls back to less specific pattern', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'App\\*' => 'warning',
                'App\\Services\\*' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should use App\\* pattern (only match) and block debug
        $this->mockLogger->shouldNotReceive('log');

        $logger->scope('App\\Controllers\\HomeController')->debug('test');
    });

    it('prefers exact match over pattern', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment.*' => 'warning',
                'payment.stripe' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should use exact match (debug level)
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', []);

        $logger->scope('payment.stripe')->debug('test');
    });

    it('handles dot notation patterns', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'payment.*' => 'debug',
                'auth.*' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // payment.* allows debug
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'payment', []);

        $logger->scope('payment.stripe')->debug('payment');

        // auth.* requires error
        $this->mockLogger->shouldNotReceive('log');

        $logger->scope('auth.login')->info('auth');
    });

    it('suppresses logs matching pattern set to false', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'vendor.*' => false,
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should suppress even emergency logs
        $this->mockLogger->shouldNotReceive('log');

        $logger->scope('vendor.noisy-package')->emergency('critical error');
    });

    it('works with auto-detection and patterns', function () {
        $config = [
            'enabled' => true,
            'default_level' => 'info',
            'scopes' => [
                'Tibbs\\ScopedLogger\\Tests\\Fixtures\\*' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
                'skip_vendor' => true,
                'skip_paths' => ['/vendor/'],
            ],
            'include_scope_in_context' => false,
        ];

        $logger = new ScopedLogger($this->mockLogger, $config);

        // Should match pattern and allow debug
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('debug', 'test', []);

        $scope = (new Tibbs\ScopedLogger\Tests\Fixtures\TestService)->testPatternMatching($logger);
    });
});
