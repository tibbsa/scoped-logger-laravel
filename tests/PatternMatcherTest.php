<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Support\PatternMatcher;

describe('PatternMatcher', function () {
    it('matches exact patterns', function () {
        $matcher = new PatternMatcher([
            'payment' => 'debug',
            'auth' => 'error',
        ]);

        expect($matcher->findMatch('payment'))->toBe('payment')
            ->and($matcher->findMatch('auth'))->toBe('auth')
            ->and($matcher->findMatch('unknown'))->toBeNull();
    });

    it('matches wildcard asterisk patterns', function () {
        $matcher = new PatternMatcher([
            'App\\Services\\*' => 'debug',
        ]);

        expect($matcher->findMatch('App\\Services\\PaymentService'))->toBe('App\\Services\\*')
            ->and($matcher->findMatch('App\\Services\\Auth\\LoginService'))->toBe('App\\Services\\*')
            ->and($matcher->findMatch('App\\Controllers\\PaymentController'))->toBeNull();
    });

    it('matches wildcard question mark patterns', function () {
        $matcher = new PatternMatcher([
            'test?' => 'debug',
        ]);

        expect($matcher->findMatch('test1'))->toBe('test?')
            ->and($matcher->findMatch('testa'))->toBe('test?')
            ->and($matcher->findMatch('test12'))->toBeNull();
    });

    it('matches dot notation patterns', function () {
        $matcher = new PatternMatcher([
            'payment.*' => 'debug',
        ]);

        expect($matcher->findMatch('payment.stripe'))->toBe('payment.*')
            ->and($matcher->findMatch('payment.paypal'))->toBe('payment.*')
            ->and($matcher->findMatch('auth.login'))->toBeNull();
    });

    it('prefers exact matches over patterns', function () {
        $matcher = new PatternMatcher([
            'App\\Services\\*' => 'info',
            'App\\Services\\PaymentService' => 'debug',
        ]);

        // Exact match should win
        expect($matcher->findMatch('App\\Services\\PaymentService'))->toBe('App\\Services\\PaymentService');
    });

    it('prefers more specific patterns', function () {
        $matcher = new PatternMatcher([
            'App\\*' => 'info',
            'App\\Services\\*' => 'debug',
            'App\\Services\\Payment\\*' => 'warning',
        ]);

        // Most specific pattern should win
        expect($matcher->findMatch('App\\Services\\Payment\\StripeService'))
            ->toBe('App\\Services\\Payment\\*');

        // Less specific
        expect($matcher->findMatch('App\\Services\\AuthService'))
            ->toBe('App\\Services\\*');

        // Least specific
        expect($matcher->findMatch('App\\Controllers\\HomeController'))
            ->toBe('App\\*');
    });

    it('prefers longer patterns when same specificity', function () {
        $matcher = new PatternMatcher([
            'payment.*.processor' => 'debug',
            'payment.*' => 'info',
        ]);

        // Longer pattern should win
        expect($matcher->findMatch('payment.stripe.processor'))
            ->toBe('payment.*.processor');
    });

    it('prefers patterns with fewer wildcards', function () {
        $matcher = new PatternMatcher([
            'App\\*\\*\\Service' => 'info',
            'App\\Services\\*Service' => 'debug',
        ]);

        // Pattern with fewer wildcards should win
        expect($matcher->findMatch('App\\Services\\PaymentService'))
            ->toBe('App\\Services\\*Service');
    });

    it('caches match results', function () {
        $matcher = new PatternMatcher([
            'App\\Services\\*' => 'debug',
        ]);

        // First call
        $result1 = $matcher->findMatch('App\\Services\\PaymentService');

        // Get stats
        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(1);

        // Second call should use cache
        $result2 = $matcher->findMatch('App\\Services\\PaymentService');

        expect($result1)->toBe($result2);
    });

    it('clears cache', function () {
        $matcher = new PatternMatcher([
            'payment.*' => 'debug',
        ]);

        $matcher->findMatch('payment.stripe');
        $matcher->findMatch('payment.paypal');

        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(2);

        $matcher->clearCache();

        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(0);
    });

    it('caches null results', function () {
        $matcher = new PatternMatcher([
            'payment.*' => 'debug',
        ]);

        // Match that returns null
        $result = $matcher->findMatch('auth.login');
        expect($result)->toBeNull();

        // Should be cached
        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(1);
    });

    it('provides cache statistics', function () {
        $matcher = new PatternMatcher([
            'payment.*' => 'debug',
            'auth.*' => 'error',
        ]);

        $stats = $matcher->getCacheStats();

        expect($stats)->toHaveKeys(['compiled_patterns', 'cached_matches', 'cache_size_bytes'])
            ->and($stats['compiled_patterns'])->toBe(2)
            ->and($stats['cached_matches'])->toBe(0);
    });

    it('handles complex namespace patterns', function () {
        $matcher = new PatternMatcher([
            'App\\*' => 'info',
        ]);

        expect($matcher->findMatch('App\\Services\\Payment\\StripeService'))->toBe('App\\*')
            ->and($matcher->findMatch('App\\Controllers\\API\\V1\\PaymentController'))->toBe('App\\*');
    });

    it('handles mixed pattern styles', function () {
        $matcher = new PatternMatcher([
            'App\\Services\\*Service' => 'debug',
            'payment.*' => 'info',
            'exact.match' => 'warning',
        ]);

        expect($matcher->findMatch('App\\Services\\PaymentService'))->toBe('App\\Services\\*Service')
            ->and($matcher->findMatch('payment.stripe'))->toBe('payment.*')
            ->and($matcher->findMatch('exact.match'))->toBe('exact.match');
    });
});
