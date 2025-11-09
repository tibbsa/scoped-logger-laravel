<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Configuration;

use Illuminate\Support\Arr;
use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;

class Configuration
{
    /**
     * @param array<string, string|false|\Closure> $scopes
     * @param array<string, array<string, string|false|\Closure>> $channelScopes
     * @param array<int, string> $autoDetectionSkipPaths
     * @param array<int, string> $disabledChannels
     */
    public function __construct(
        protected bool $enabled = true,
        protected string $defaultLevel = 'info',
        protected array $scopes = [],
        protected string $unknownScopeHandling = 'exception',
        protected array $channelScopes = [],
        protected bool $autoDetectionEnabled = true,
        protected string $autoDetectionProperty = 'log_scope',
        protected int $autoDetectionStackDepth = 10,
        protected bool $autoDetectionSkipVendor = true,
        protected array $autoDetectionSkipPaths = ['/vendor/', '/bootstrap/'],
        protected array $disabledChannels = [],
        protected bool $includeScopeInContext = true,
        protected string $scopeContextKey = 'scope',
        protected bool $includeMetadata = false,
        protected bool $metadataSkipVendor = true,
        protected bool $metadataRelativePaths = true,
        protected ?string $metadataBasePath = null,
        protected bool $debugMode = false,
    ) {
    }

    /**
     * Create configuration from array (for backwards compatibility)
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        try {
            $autoDetection = Arr::array($config, 'auto_detection', []);

            // Extract complex array types with proper type hints
            /** @var array<string, string|false|\Closure> $scopes */
            $scopes = Arr::array($config, 'scopes', []);

            /** @var array<string, array<string, string|false|\Closure>> $channelScopes */
            $channelScopes = Arr::array($config, 'channel_scopes', []);

            /** @var array<int, string> $autoDetectionSkipPaths */
            $autoDetectionSkipPaths = Arr::array($autoDetection, 'skip_paths', ['/vendor/', '/bootstrap/']);

            /** @var array<int, string> $disabledChannels */
            $disabledChannels = Arr::array($config, 'disabled_channels', []);

            // Handle nullable metadata_base_path
            $metadataBasePath = $config['metadata_base_path'] ?? null;
            if ($metadataBasePath !== null && !is_string($metadataBasePath)) {
                throw new \InvalidArgumentException('metadata_base_path must be a string or null');
            }

            return new self(
                enabled: Arr::boolean($config, 'enabled', true),
                defaultLevel: Arr::string($config, 'default_level', 'info'),
                scopes: $scopes,
                unknownScopeHandling: Arr::string($config, 'unknown_scope_handling', 'exception'),
                channelScopes: $channelScopes,
                autoDetectionEnabled: Arr::boolean($autoDetection, 'enabled', true),
                autoDetectionProperty: Arr::string($autoDetection, 'property', 'log_scope'),
                autoDetectionStackDepth: Arr::integer($autoDetection, 'stack_depth', 10),
                autoDetectionSkipVendor: Arr::boolean($autoDetection, 'skip_vendor', true),
                autoDetectionSkipPaths: $autoDetectionSkipPaths,
                disabledChannels: $disabledChannels,
                includeScopeInContext: Arr::boolean($config, 'include_scope_in_context', true),
                scopeContextKey: Arr::string($config, 'scope_context_key', 'scope'),
                includeMetadata: Arr::boolean($config, 'include_metadata', false),
                metadataSkipVendor: Arr::boolean($config, 'metadata_skip_vendor', true),
                metadataRelativePaths: Arr::boolean($config, 'metadata_relative_paths', true),
                metadataBasePath: $metadataBasePath,
                debugMode: Arr::boolean($config, 'debug_mode', false),
            );
        } catch (\InvalidArgumentException $e) {
            throw InvalidScopeConfigurationException::invalidConfigType($e->getMessage());
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function defaultLevel(): string
    {
        return $this->defaultLevel;
    }

    /**
     * @return array<string, string|false|\Closure>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function unknownScopeHandling(): string
    {
        return $this->unknownScopeHandling;
    }

    /**
     * @return array<string, array<string, string|false|\Closure>>
     */
    public function channelScopes(): array
    {
        return $this->channelScopes;
    }

    /**
     * @return array<string, string|false|\Closure>
     */
    public function scopesForChannel(string $channel): array
    {
        return $this->channelScopes[$channel] ?? [];
    }

    public function autoDetectionEnabled(): bool
    {
        return $this->autoDetectionEnabled;
    }

    public function autoDetectionProperty(): string
    {
        return $this->autoDetectionProperty;
    }

    public function autoDetectionStackDepth(): int
    {
        return $this->autoDetectionStackDepth;
    }

    public function autoDetectionSkipVendor(): bool
    {
        return $this->autoDetectionSkipVendor;
    }

    /**
     * @return array<int, string>
     */
    public function autoDetectionSkipPaths(): array
    {
        return $this->autoDetectionSkipPaths;
    }

    /**
     * @return array<int, string>
     */
    public function disabledChannels(): array
    {
        return $this->disabledChannels;
    }

    public function includeScopeInContext(): bool
    {
        return $this->includeScopeInContext;
    }

    public function scopeContextKey(): string
    {
        return $this->scopeContextKey;
    }

    public function includeMetadata(): bool
    {
        return $this->includeMetadata;
    }

    public function metadataSkipVendor(): bool
    {
        return $this->metadataSkipVendor;
    }

    public function metadataRelativePaths(): bool
    {
        return $this->metadataRelativePaths;
    }

    public function metadataBasePath(): ?string
    {
        return $this->metadataBasePath;
    }

    public function debugMode(): bool
    {
        return $this->debugMode;
    }
}
