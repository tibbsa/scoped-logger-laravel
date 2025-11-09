<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Configuration;

use Tibbs\ScopedLogger\Exceptions\InvalidScopeConfigurationException;

class Validator
{
    protected const VALID_LOG_LEVELS = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ];

    protected const VALID_UNKNOWN_SCOPE_HANDLING = [
        'exception',
        'log',
        'ignore',
    ];

    public function validate(Configuration $config): void
    {
        $this->validateDefaultLevel($config);
        $this->validateScopes($config);
        $this->validateUnknownScopeHandling($config);
    }

    protected function validateDefaultLevel(Configuration $config): void
    {
        $level = $config->defaultLevel();

        if (! in_array($level, self::VALID_LOG_LEVELS)) {
            throw InvalidScopeConfigurationException::invalidLevel($level, 'default_level');
        }
    }

    protected function validateScopes(Configuration $config): void
    {
        foreach ($config->scopes() as $scope => $level) {
            // Skip closures (validated at runtime) and false (suppression)
            if ($level !== false && ! $level instanceof \Closure) {
                if (! in_array($level, self::VALID_LOG_LEVELS)) {
                    throw InvalidScopeConfigurationException::invalidLevel($level, "scopes.{$scope}");
                }
            }
        }
    }

    protected function validateUnknownScopeHandling(Configuration $config): void
    {
        $handling = $config->unknownScopeHandling();

        if (! in_array($handling, self::VALID_UNKNOWN_SCOPE_HANDLING)) {
            throw InvalidScopeConfigurationException::invalidUnknownScopeHandling($handling);
        }
    }
}
