<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Exceptions;

use Exception;

class UnknownScopeException extends Exception
{
    /**
     * Create a new exception for an unknown scope.
     */
    public static function forScope(string $scope): self
    {
        return new self(
            "Unknown scope '{$scope}' used but not configured. ".
            "Add '{$scope}' to your config/scoped-logger.php scopes configuration, ".
            "or set 'unknown_scope_handling' to 'ignore' or 'log' to allow unconfigured scopes."
        );
    }

    /**
     * Create a new exception for multiple unknown scopes.
     *
     * @param  array<int, string>  $scopes
     */
    public static function forScopes(array $scopes): self
    {
        $scopeList = implode("', '", $scopes);
        $count = count($scopes);
        $plural = $count > 1 ? 's' : '';

        return new self(
            "Unknown scope{$plural} '{$scopeList}' used but not configured. ".
            "Add these scope{$plural} to your config/scoped-logger.php scopes configuration, ".
            "or set 'unknown_scope_handling' to 'ignore' or 'log' to allow unconfigured scopes."
        );
    }
}
