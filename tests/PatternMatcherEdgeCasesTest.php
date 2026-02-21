<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Support\PatternMatcher;

describe('PatternMatcher Edge Cases', function () {
    it('uses wildcard count as tiebreaker for same-length patterns', function () {
        // Two patterns that are the same length but have different wildcard counts
        // 'App\Svc\*\Pay' has 1 wildcard  (14 chars)
        // 'App\*\?\Pay??'  would be different
        // Let's create patterns where length is same but wildcard count differs
        $matcher = new PatternMatcher([
            'App\Svc\*' => 'debug',    // 9 chars, 1 wildcard
            'App\Sv?\*' => 'warning',  // 9 chars, 2 wildcards
        ]);

        // 'App\Svc\Anything' matches 'App\Svc\*' (1 wildcard) and 'App\Sv?\*' (2 wildcards)
        // The one with fewer wildcards should win
        $result = $matcher->findMatch('App\Svc\Anything');

        expect($result)->toBe('App\Svc\*');
    });

    it('prefers exact match over any pattern', function () {
        $matcher = new PatternMatcher([
            'App\Services\Payment' => 'error',
            'App\Services\*' => 'debug',
        ]);

        $result = $matcher->findMatch('App\Services\Payment');

        expect($result)->toBe('App\Services\Payment');
    });

    it('prefers longer patterns over shorter ones', function () {
        $matcher = new PatternMatcher([
            'App\*' => 'warning',
            'App\Services\*' => 'debug',
        ]);

        $result = $matcher->findMatch('App\Services\PaymentService');

        expect($result)->toBe('App\Services\*');
    });

    it('clears match cache', function () {
        $matcher = new PatternMatcher([
            'App\*' => 'debug',
        ]);

        // Populate cache
        $matcher->findMatch('App\Services\Payment');
        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(1);

        // Clear cache
        $matcher->clearCache();
        $stats = $matcher->getCacheStats();
        expect($stats['cached_matches'])->toBe(0);
    });
});
