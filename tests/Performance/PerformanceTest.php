<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\ScopedLogger;
use Tibbs\ScopedLogger\Tests\Performance\Fixtures\DeepNestingService;
use Tibbs\ScopedLogger\Tests\Performance\Fixtures\NullLogger;
use Tibbs\ScopedLogger\Tests\Performance\Fixtures\PerformanceTestService;
use Tibbs\ScopedLogger\Tests\Performance\Fixtures\ServiceWithMethodScope;
use Tibbs\ScopedLogger\Tests\Performance\Fixtures\ServiceWithPropertyScope;
use Tibbs\ScopedLogger\Tests\Performance\PerformanceBenchmark;

/**
 * Performance Test Suite for Scoped Logger
 *
 * This test suite measures the performance overhead of various scoped-logger features
 * compared to direct logging. Run these tests separately from the main test suite:
 *
 *     composer test-performance
 *
 * Or directly:
 *
 *     vendor/bin/pest tests/Performance/PerformanceTest.php --group=performance
 *
 * These tests are excluded from the normal test run to avoid slowing down CI.
 */
describe('Performance Benchmarks', function () {
    beforeEach(function () {
        $this->benchmark = new PerformanceBenchmark;
        $this->benchmark->setDefaultIterations(100_000);
        $this->nullLogger = new NullLogger;
    });

    it('baseline direct logging', function () {
        $logger = $this->nullLogger;

        $this->benchmark->warmup(fn () => $logger->info('test message'));

        $result = $this->benchmark->run(
            'baseline_direct',
            fn () => $logger->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('scoped logger disabled', function () {
        $config = Configuration::fromArray([
            'enabled' => false,
            'default_level' => 'debug',
            'scopes' => [],
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->info('test message'));

        $result = $this->benchmark->run(
            'scoped_disabled',
            fn () => $scopedLogger->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('scoped logger enabled no scopes', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->info('test message'));

        $result = $this->benchmark->run(
            'scoped_no_scopes',
            fn () => $scopedLogger->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('explicit scope exact match', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'payment' => 'debug',
                'api' => 'info',
                'auth' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->info('test'));

        $result = $this->benchmark->run(
            'explicit_scope',
            fn () => $scopedLogger->scope('payment')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('explicit scope pattern match', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'App\\Services\\*' => 'debug',
                'App\\Http\\Controllers\\*' => 'info',
                'App\\Models\\*' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(
            fn () => $scopedLogger->scope('App\\Services\\PaymentService')->info('test')
        );

        $result = $this->benchmark->run(
            'pattern_match',
            fn () => $scopedLogger->scope('App\\Services\\PaymentService')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('pattern match many patterns', function () {
        $scopes = [];
        for ($i = 0; $i < 50; $i++) {
            $scopes["App\\Domain{$i}\\*"] = 'info';
        }
        $scopes['App\\Services\\*'] = 'debug';

        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => $scopes,
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(
            fn () => $scopedLogger->scope('App\\Services\\PaymentService')->info('test')
        );

        $result = $this->benchmark->run(
            'many_patterns',
            fn () => $scopedLogger->scope('App\\Services\\PaymentService')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('multiple scopes', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'payment' => 'debug',
                'api' => 'info',
                'auth' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(
            fn () => $scopedLogger->scope(['payment', 'api'])->info('test')
        );

        $result = $this->benchmark->run(
            'multiple_scopes',
            fn () => $scopedLogger->scope(['payment', 'api', 'auth'])->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('auto detection fqcn', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                PerformanceTestService::class => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
            ],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $service = new PerformanceTestService($scopedLogger);

        $this->benchmark->warmup(fn () => $service->logMessage('test'));

        $result = $this->benchmark->run(
            'auto_detect_fqcn',
            fn () => $service->logMessage('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('auto detection property', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'perf-property-scope' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
            ],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $service = new ServiceWithPropertyScope($scopedLogger);

        $this->benchmark->warmup(fn () => $service->logMessage('test'));

        $result = $this->benchmark->run(
            'auto_detect_property',
            fn () => $service->logMessage('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('auto detection method', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'perf-method-scope' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
            ],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $service = new ServiceWithMethodScope($scopedLogger);

        $this->benchmark->warmup(fn () => $service->logMessage('test'));

        $result = $this->benchmark->run(
            'auto_detect_method',
            fn () => $service->logMessage('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('auto detection deep stack', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'deep-nesting-scope' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 15,
            ],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $service = new DeepNestingService($scopedLogger);

        $this->benchmark->warmup(fn () => $service->logWithDepth('test', 10));

        $result = $this->benchmark->run(
            'auto_detect_deep',
            fn () => $service->logWithDepth('test message', 10)
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('metadata extraction', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => true,
            'metadata' => [
                'skip_vendor' => true,
                'relative_paths' => true,
            ],
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->info('test'));

        $result = $this->benchmark->run(
            'with_metadata',
            fn () => $scopedLogger->scope('payment')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('debug mode enabled', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => true,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->info('test'));

        $result = $this->benchmark->run(
            'debug_mode',
            fn () => $scopedLogger->scope('payment')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('all features enabled', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'perf-property-scope' => 'debug',
            ],
            'auto_detection' => [
                'enabled' => true,
                'stack_depth' => 10,
            ],
            'include_scope_in_context' => true,
            'include_metadata' => true,
            'metadata' => [
                'skip_vendor' => true,
                'relative_paths' => true,
            ],
            'debug_mode' => true,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $service = new ServiceWithPropertyScope($scopedLogger);

        $this->benchmark->warmup(fn () => $service->logMessage('test'));

        $result = $this->benchmark->run(
            'all_features',
            fn () => $service->logMessage('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('log filtering suppressed', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'error',
            'scopes' => [
                'payment' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->debug('test'));

        $result = $this->benchmark->run(
            'filtered_out',
            fn () => $scopedLogger->scope('payment')->debug('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('scope completely suppressed', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'suppressed' => false,
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('suppressed')->info('test'));

        $result = $this->benchmark->run(
            'completely_suppressed',
            fn () => $scopedLogger->scope('suppressed')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('runtime level override', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'payment' => 'warning',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);
        $scopedLogger->setRuntimeLevel('payment', 'debug');

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->debug('test'));

        $result = $this->benchmark->run(
            'runtime_override',
            fn () => $scopedLogger->scope('payment')->debug('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('large context data', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $largeContext = [];
        for ($i = 0; $i < 50; $i++) {
            $largeContext["key_{$i}"] = str_repeat('value', 20);
        }

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->info('test', $largeContext));

        $result = $this->benchmark->run(
            'large_context',
            fn () => $scopedLogger->scope('payment')->info('test message', $largeContext)
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('shared context', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [
                'payment' => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $scopedLogger->withContext([
            'request_id' => 'abc123',
            'user_id' => 42,
            'session_id' => 'sess_xyz',
        ]);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('payment')->info('test'));

        $result = $this->benchmark->run(
            'shared_context',
            fn () => $scopedLogger->scope('payment')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');

    it('conditional closure level', function () {
        $config = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'conditional' => fn () => 'debug',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);

        $scopedLogger = new ScopedLogger($this->nullLogger, $config);

        $this->benchmark->warmup(fn () => $scopedLogger->scope('conditional')->info('test'));

        $result = $this->benchmark->run(
            'closure_level',
            fn () => $scopedLogger->scope('conditional')->info('test message', ['key' => 'value'])
        );

        expect($result['ops_per_second'])->toBeGreaterThan(0);
    })->group('performance');
});

describe('Performance Summary', function () {
    beforeEach(function () {
        $this->benchmark = new PerformanceBenchmark;
        $this->benchmark->setDefaultIterations(100_000);
        $this->nullLogger = new NullLogger;
    });

    it('overhead breakdown', function () {
        $benchmarks = [];

        // =====================================================================
        // BASELINE TESTS
        // =====================================================================

        // 1. Baseline: Direct logging (no scoped logger)
        $benchmarks['01. Baseline (Direct)'] = fn () => $this->nullLogger->info('test', ['key' => 'value']);

        // 2. Scoped Logger disabled (passthrough)
        $disabledConfig = Configuration::fromArray([
            'enabled' => false,
            'default_level' => 'debug',
            'scopes' => [],
        ]);
        $disabledLogger = new ScopedLogger($this->nullLogger, $disabledConfig);
        $benchmarks['02. ScopedLogger Disabled'] = fn () => $disabledLogger->info('test', ['key' => 'value']);

        // 3. Scoped Logger enabled, no scopes configured
        $noScopesConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => [],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
        ]);
        $noScopesLogger = new ScopedLogger($this->nullLogger, $noScopesConfig);
        $benchmarks['03. Enabled (No Scopes)'] = fn () => $noScopesLogger->info('test', ['key' => 'value']);

        // =====================================================================
        // EXPLICIT SCOPE TESTS
        // =====================================================================

        // 4. Minimal config with explicit scope (exact match)
        $minimalConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['payment' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $minimalLogger = new ScopedLogger($this->nullLogger, $minimalConfig);
        $benchmarks['04. Explicit Scope (Exact)'] = fn () => $minimalLogger->scope('payment')->info('test', ['key' => 'value']);

        // 5. Pattern matching (3 patterns)
        $patternConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'App\\Services\\*' => 'debug',
                'App\\Http\\*' => 'info',
                'App\\Models\\*' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $patternLogger = new ScopedLogger($this->nullLogger, $patternConfig);
        $benchmarks['05. Pattern Match (3)'] = fn () => $patternLogger->scope('App\\Services\\PaymentService')->info('test', ['key' => 'value']);

        // 6. Pattern matching with many patterns (50+)
        $manyPatternScopes = [];
        for ($i = 0; $i < 50; $i++) {
            $manyPatternScopes["App\\Domain{$i}\\*"] = 'info';
        }
        $manyPatternScopes['App\\Services\\*'] = 'debug';
        $manyPatternsConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => $manyPatternScopes,
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $manyPatternsLogger = new ScopedLogger($this->nullLogger, $manyPatternsConfig);
        $benchmarks['06. Pattern Match (50+)'] = fn () => $manyPatternsLogger->scope('App\\Services\\PaymentService')->info('test', ['key' => 'value']);

        // 7. Multiple scopes (3 scopes)
        $multipleScopesConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [
                'payment' => 'debug',
                'api' => 'info',
                'auth' => 'error',
            ],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $multipleScopesLogger = new ScopedLogger($this->nullLogger, $multipleScopesConfig);
        $benchmarks['07. Multiple Scopes (3)'] = fn () => $multipleScopesLogger->scope(['payment', 'api', 'auth'])->info('test', ['key' => 'value']);

        // =====================================================================
        // AUTO-DETECTION TESTS
        // =====================================================================

        // 8. Auto-detection via FQCN
        $autoDetectFqcnConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => [PerformanceTestService::class => 'debug'],
            'auto_detection' => ['enabled' => true, 'stack_depth' => 10],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $autoDetectFqcnLogger = new ScopedLogger($this->nullLogger, $autoDetectFqcnConfig);
        $autoDetectFqcnService = new PerformanceTestService($autoDetectFqcnLogger);
        $benchmarks['08. Auto-Detect (FQCN)'] = fn () => $autoDetectFqcnService->logMessage('test', ['key' => 'value']);

        // 9. Auto-detection via property
        $autoDetectPropertyConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['perf-property-scope' => 'debug'],
            'auto_detection' => ['enabled' => true, 'stack_depth' => 10],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $autoDetectPropertyLogger = new ScopedLogger($this->nullLogger, $autoDetectPropertyConfig);
        $autoDetectPropertyService = new ServiceWithPropertyScope($autoDetectPropertyLogger);
        $benchmarks['09. Auto-Detect (Property)'] = fn () => $autoDetectPropertyService->logMessage('test', ['key' => 'value']);

        // 10. Auto-detection via method
        $autoDetectMethodConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['perf-method-scope' => 'debug'],
            'auto_detection' => ['enabled' => true, 'stack_depth' => 10],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $autoDetectMethodLogger = new ScopedLogger($this->nullLogger, $autoDetectMethodConfig);
        $autoDetectMethodService = new ServiceWithMethodScope($autoDetectMethodLogger);
        $benchmarks['10. Auto-Detect (Method)'] = fn () => $autoDetectMethodService->logMessage('test', ['key' => 'value']);

        // 11. Auto-detection with deep call stack
        $autoDetectDeepConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['deep-nesting-scope' => 'debug'],
            'auto_detection' => ['enabled' => true, 'stack_depth' => 15],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $autoDetectDeepLogger = new ScopedLogger($this->nullLogger, $autoDetectDeepConfig);
        $autoDetectDeepService = new DeepNestingService($autoDetectDeepLogger);
        $benchmarks['11. Auto-Detect (Deep Stack)'] = fn () => $autoDetectDeepService->logWithDepth('test', 10);

        // =====================================================================
        // FEATURE OVERHEAD TESTS
        // =====================================================================

        // 12. With metadata extraction
        $metadataConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['payment' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => true,
            'metadata' => ['skip_vendor' => true, 'relative_paths' => true],
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $metadataLogger = new ScopedLogger($this->nullLogger, $metadataConfig);
        $benchmarks['12. With Metadata'] = fn () => $metadataLogger->scope('payment')->info('test', ['key' => 'value']);

        // 13. Debug mode enabled
        $debugModeConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['payment' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => true,
            'unknown_scope_handling' => 'ignore',
        ]);
        $debugModeLogger = new ScopedLogger($this->nullLogger, $debugModeConfig);
        $benchmarks['13. Debug Mode'] = fn () => $debugModeLogger->scope('payment')->info('test', ['key' => 'value']);

        // 14. Runtime level override
        $runtimeConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['payment' => 'warning'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $runtimeLogger = new ScopedLogger($this->nullLogger, $runtimeConfig);
        $runtimeLogger->setRuntimeLevel('payment', 'debug');
        $benchmarks['14. Runtime Override'] = fn () => $runtimeLogger->scope('payment')->debug('test', ['key' => 'value']);

        // 15. Closure-based level
        $closureConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['conditional' => fn () => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $closureLogger = new ScopedLogger($this->nullLogger, $closureConfig);
        $benchmarks['15. Closure Level'] = fn () => $closureLogger->scope('conditional')->info('test', ['key' => 'value']);

        // 16. Shared context
        $sharedContextConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['payment' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $sharedContextLogger = new ScopedLogger($this->nullLogger, $sharedContextConfig);
        $sharedContextLogger->withContext(['request_id' => 'abc123', 'user_id' => 42, 'session_id' => 'sess_xyz']);
        $benchmarks['16. Shared Context'] = fn () => $sharedContextLogger->scope('payment')->info('test', ['key' => 'value']);

        // 17. Large context data (50 keys)
        $largeContextConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['payment' => 'debug'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => true,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $largeContextLogger = new ScopedLogger($this->nullLogger, $largeContextConfig);
        $largeContext = [];
        for ($i = 0; $i < 50; $i++) {
            $largeContext["key_{$i}"] = str_repeat('value', 20);
        }
        $benchmarks['17. Large Context (50 keys)'] = fn () => $largeContextLogger->scope('payment')->info('test', $largeContext);

        // =====================================================================
        // FULL FEATURES & FILTERING TESTS
        // =====================================================================

        // 18. All features enabled
        $fullConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'warning',
            'scopes' => ['perf-property-scope' => 'debug'],
            'auto_detection' => ['enabled' => true, 'stack_depth' => 10],
            'include_scope_in_context' => true,
            'include_metadata' => true,
            'metadata' => ['skip_vendor' => true, 'relative_paths' => true],
            'debug_mode' => true,
            'unknown_scope_handling' => 'ignore',
        ]);
        $fullLogger = new ScopedLogger($this->nullLogger, $fullConfig);
        $fullService = new ServiceWithPropertyScope($fullLogger);
        $benchmarks['18. All Features Enabled'] = fn () => $fullService->logMessage('test', ['key' => 'value']);

        // 19. Filtered out logs (early exit)
        $filteredConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'error',
            'scopes' => ['payment' => 'error'],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $filteredLogger = new ScopedLogger($this->nullLogger, $filteredConfig);
        $benchmarks['19. Filtered Out (Early Exit)'] = fn () => $filteredLogger->scope('payment')->debug('test', ['key' => 'value']);

        // 20. Scope completely suppressed
        $suppressedConfig = Configuration::fromArray([
            'enabled' => true,
            'default_level' => 'debug',
            'scopes' => ['suppressed' => false],
            'auto_detection' => ['enabled' => false],
            'include_scope_in_context' => false,
            'include_metadata' => false,
            'debug_mode' => false,
            'unknown_scope_handling' => 'ignore',
        ]);
        $suppressedLogger = new ScopedLogger($this->nullLogger, $suppressedConfig);
        $benchmarks['20. Scope Suppressed'] = fn () => $suppressedLogger->scope('suppressed')->info('test', ['key' => 'value']);

        // Run all benchmarks
        foreach ($benchmarks as $name => $callable) {
            $this->benchmark->warmup($callable);
            $this->benchmark->run($name, $callable);
        }

        // Output results to STDERR so they appear even with PHPUnit output buffering
        outputBenchmarkResults($this->benchmark, 100_000);

        expect($this->benchmark->getResults())->not->toBeEmpty();
    })->group('performance');
});

/**
 * Output benchmark results to STDERR to bypass PHPUnit output buffering
 */
function outputBenchmarkResults(PerformanceBenchmark $benchmark, int $iterations): void
{
    $results = $benchmark->getResults();
    $stderr = fopen('php://stderr', 'w');

    if ($stderr === false) {
        return;
    }

    fwrite($stderr, "\n\n");
    fwrite($stderr, str_repeat('=', 100)."\n");
    fwrite($stderr, "                         SCOPED LOGGER PERFORMANCE BENCHMARK RESULTS\n");
    fwrite($stderr, str_repeat('=', 100)."\n");
    fwrite($stderr, sprintf(
        "%-35s | %12s | %15s | %15s | %12s\n",
        'Benchmark',
        'Duration',
        'Ops/sec',
        'Avg/op',
        'Peak Memory'
    ));
    fwrite($stderr, str_repeat('-', 100)."\n");

    foreach ($results as $name => $result) {
        fwrite($stderr, sprintf(
            "%-35s | %12s | %15s | %15s | %12s\n",
            $name,
            PerformanceBenchmark::formatDuration($result['duration']),
            number_format($result['ops_per_second'], 0),
            PerformanceBenchmark::formatDuration($result['avg_ms_per_op']),
            PerformanceBenchmark::formatBytes($result['memory_peak'])
        ));
    }

    fwrite($stderr, str_repeat('=', 100)."\n");

    // Print overhead comparisons vs baseline
    fwrite($stderr, "\n");
    fwrite($stderr, str_repeat('=', 100)."\n");
    fwrite($stderr, "                    OVERHEAD vs BASELINE (Direct PSR-3 Logger Call)\n");
    fwrite($stderr, str_repeat('=', 100)."\n");

    $resultKeys = array_keys($results);
    $baselineName = $resultKeys[0] ?? null;
    $baseline = $baselineName ? $results[$baselineName] : null;

    if ($baseline) {
        foreach ($results as $name => $result) {
            if ($name === $baselineName) {
                fwrite($stderr, sprintf("    %-35s: (baseline)\n", $name));

                continue;
            }

            // Calculate overhead per operation in microseconds
            $baselineAvgUs = $baseline['avg_ms_per_op'] * 1000;
            $currentAvgUs = $result['avg_ms_per_op'] * 1000;
            $overheadUs = $currentAvgUs - $baselineAvgUs;

            fwrite($stderr, sprintf(
                "    %-35s: +%.2f µs/op (%.2f µs total)\n",
                $name,
                $overheadUs,
                $currentAvgUs
            ));
        }
    }

    // Print comparison vs minimal scoped logger
    fwrite($stderr, "\n");
    fwrite($stderr, str_repeat('=', 100)."\n");
    fwrite($stderr, "                    OVERHEAD vs MINIMAL SCOPED LOGGER\n");
    fwrite($stderr, str_repeat('=', 100)."\n");

    $minimalName = $resultKeys[3] ?? null; // "04. Explicit Scope (Exact)"
    $minimal = $minimalName ? $results[$minimalName] : null;

    if ($minimal) {
        foreach ($results as $name => $result) {
            if ($name === $baselineName) {
                continue;
            }

            $minimalAvgUs = $minimal['avg_ms_per_op'] * 1000;
            $currentAvgUs = $result['avg_ms_per_op'] * 1000;
            $overheadPct = (($result['duration'] - $minimal['duration']) / $minimal['duration']) * 100;

            if ($name === $minimalName) {
                fwrite($stderr, sprintf("       %-35s: (baseline for features)\n", $name));

                continue;
            }

            $indicator = $overheadPct > 100 ? '[SLOW] ' : ($overheadPct > 20 ? '[    ] ' : '[FAST] ');

            fwrite($stderr, sprintf(
                "%s%-35s: %+6.1f%% (%+.2f µs/op)\n",
                $indicator,
                $name,
                $overheadPct,
                $currentAvgUs - $minimalAvgUs
            ));
        }
    }

    fwrite($stderr, str_repeat('=', 100)."\n");
    fwrite($stderr, sprintf("\nIterations per benchmark: %s\n", number_format($iterations)));
    fwrite($stderr, "Note: All times are per-operation. Lower is better.\n");
    fwrite($stderr, "\n");

    fclose($stderr);
}
