<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;

describe('Configuration', function () {
    it('throws exception for non-string metadata_base_path', function () {
        expect(fn () => Configuration::fromArray([
            'metadata_base_path' => 123,
        ]))->toThrow(InvalidScopeConfigurationException::class);
    });

    it('returns metadata configuration values', function () {
        $config = Configuration::fromArray([
            'include_metadata' => true,
            'metadata_skip_vendor' => false,
            'metadata_relative_paths' => false,
            'metadata_base_path' => '/custom/path',
            'debug_mode' => true,
        ]);

        expect($config->includeMetadata())->toBeTrue()
            ->and($config->metadataSkipVendor())->toBeFalse()
            ->and($config->metadataRelativePaths())->toBeFalse()
            ->and($config->metadataBasePath())->toBe('/custom/path')
            ->and($config->debugMode())->toBeTrue();
    });

    it('uses default values when config array is empty', function () {
        $config = Configuration::fromArray([]);

        expect($config->isEnabled())->toBeTrue()
            ->and($config->defaultLevel())->toBe('info')
            ->and($config->scopes())->toBe([])
            ->and($config->unknownScopeHandling())->toBe('exception')
            ->and($config->channelScopes())->toBe([])
            ->and($config->autoDetectionEnabled())->toBeTrue()
            ->and($config->autoDetectionProperty())->toBe('log_scope')
            ->and($config->autoDetectionStackDepth())->toBe(10)
            ->and($config->autoDetectionSkipVendor())->toBeTrue()
            ->and($config->disabledChannels())->toBe([])
            ->and($config->includeScopeInContext())->toBeTrue()
            ->and($config->scopeContextKey())->toBe('scope')
            ->and($config->includeMetadata())->toBeFalse()
            ->and($config->metadataSkipVendor())->toBeTrue()
            ->and($config->metadataRelativePaths())->toBeTrue()
            ->and($config->metadataBasePath())->toBeNull()
            ->and($config->debugMode())->toBeFalse();
    });

    it('accepts null metadata_base_path', function () {
        $config = Configuration::fromArray([
            'metadata_base_path' => null,
        ]);

        expect($config->metadataBasePath())->toBeNull();
    });

    it('accepts string metadata_base_path', function () {
        $config = Configuration::fromArray([
            'metadata_base_path' => '/some/path',
        ]);

        expect($config->metadataBasePath())->toBe('/some/path');
    });

    it('returns scopes for specific channel', function () {
        $config = Configuration::fromArray([
            'channel_scopes' => [
                'daily' => ['payment' => 'debug'],
                'slack' => ['auth' => 'error'],
            ],
        ]);

        expect($config->scopesForChannel('daily'))->toBe(['payment' => 'debug'])
            ->and($config->scopesForChannel('slack'))->toBe(['auth' => 'error'])
            ->and($config->scopesForChannel('nonexistent'))->toBe([]);
    });
});
