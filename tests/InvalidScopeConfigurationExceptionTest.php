<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;

describe('InvalidScopeConfigurationException', function () {
    it('creates exception for invalid pattern', function () {
        $exception = InvalidScopeConfigurationException::invalidPattern('bad[pattern', 'contains invalid brackets');

        expect($exception)->toBeInstanceOf(InvalidScopeConfigurationException::class)
            ->and($exception->getMessage())->toContain('bad[pattern')
            ->and($exception->getMessage())->toContain('contains invalid brackets')
            ->and($exception->getMessage())->toContain('Patterns support wildcards');
    });

    it('creates exception for invalid config type', function () {
        $exception = InvalidScopeConfigurationException::invalidConfigType('expected string, got integer');

        expect($exception)->toBeInstanceOf(InvalidScopeConfigurationException::class)
            ->and($exception->getMessage())->toContain('expected string, got integer')
            ->and($exception->getMessage())->toContain('Check your config/scoped-logger.php');
    });

    it('creates exception for invalid level', function () {
        $exception = InvalidScopeConfigurationException::invalidLevel('invalid', 'scopes.payment');

        expect($exception)->toBeInstanceOf(InvalidScopeConfigurationException::class)
            ->and($exception->getMessage())->toContain('invalid')
            ->and($exception->getMessage())->toContain('scopes.payment');
    });

    it('creates exception for empty scope', function () {
        $exception = InvalidScopeConfigurationException::emptyScope();

        expect($exception)->toBeInstanceOf(InvalidScopeConfigurationException::class)
            ->and($exception->getMessage())->toContain('cannot be empty');
    });

    it('creates exception for invalid unknown scope handling', function () {
        $exception = InvalidScopeConfigurationException::invalidUnknownScopeHandling('invalid');

        expect($exception)->toBeInstanceOf(InvalidScopeConfigurationException::class)
            ->and($exception->getMessage())->toContain('invalid')
            ->and($exception->getMessage())->toContain('exception')
            ->and($exception->getMessage())->toContain('log')
            ->and($exception->getMessage())->toContain('ignore');
    });
});
