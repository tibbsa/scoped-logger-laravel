<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Support;

class PatternMatcher
{
    /**
     * Cache of compiled patterns
     *
     * @var array<string, string>
     */
    protected array $compiledPatterns = [];

    /**
     * Cache of scope match results
     *
     * @var array<string, string|null>
     */
    protected array $matchCache = [];

    /**
     * @param array<string, string|false|\Closure> $scopePatterns
     */
    public function __construct(
        protected array $scopePatterns
    ) {
        $this->compilePatterns();
    }

    /**
     * Find the best matching pattern for a given scope
     * Returns the matched pattern key or null if no match
     */
    public function findMatch(string $scope): ?string
    {
        // Check cache first
        if (isset($this->matchCache[$scope])) {
            return $this->matchCache[$scope];
        }

        $matches = [];

        foreach ($this->compiledPatterns as $pattern => $regex) {
            if ($this->matches($scope, $pattern, $regex)) {
                $matches[] = $pattern;
            }
        }

        // If no matches, cache null result
        if (empty($matches)) {
            $this->matchCache[$scope] = null;

            return null;
        }

        // Sort by specificity (longest/most specific first)
        usort($matches, fn ($a, $b) => $this->compareSpecificity($a, $b));

        // Return most specific match
        $bestMatch = $matches[0];
        $this->matchCache[$scope] = $bestMatch;

        return $bestMatch;
    }

    /**
     * Check if a scope matches a pattern
     */
    protected function matches(string $scope, string $pattern, string $regex): bool
    {
        // Exact match (no wildcards)
        if ($pattern === $scope) {
            return true;
        }

        // Pattern match
        return (bool) preg_match($regex, $scope);
    }

    /**
     * Compile all patterns into regex
     */
    protected function compilePatterns(): void
    {
        foreach ($this->scopePatterns as $pattern => $level) {
            $this->compiledPatterns[$pattern] = $this->compilePattern($pattern);
        }
    }

    /**
     * Compile a single pattern into a regex
     */
    protected function compilePattern(string $pattern): string
    {
        // Escape special regex characters except * and ?
        $regex = preg_quote($pattern, '/');

        // Replace wildcards:
        // \* (escaped asterisk) -> .* (match any characters)
        // \? (escaped question mark) -> . (match single character)
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);

        // Anchor to start and end
        return '/^'.$regex.'$/';
    }

    /**
     * Compare specificity of two patterns
     * Returns negative if $a is more specific, positive if $b is more specific
     */
    protected function compareSpecificity(string $a, string $b): int
    {
        // 1. Exact matches (no wildcards) are most specific
        $aHasWildcard = str_contains($a, '*') || str_contains($a, '?');
        $bHasWildcard = str_contains($b, '*') || str_contains($b, '?');

        if (!$aHasWildcard && $bHasWildcard) {
            return -1; // $a more specific
        }
        if ($aHasWildcard && !$bHasWildcard) {
            return 1; // $b more specific
        }

        // 2. Longer patterns are more specific
        $lengthDiff = strlen($b) - strlen($a);
        if ($lengthDiff !== 0) {
            return $lengthDiff;
        }

        // 3. Patterns with fewer wildcards are more specific
        $aWildcardCount = substr_count($a, '*') + substr_count($a, '?');
        $bWildcardCount = substr_count($b, '*') + substr_count($b, '?');

        return $aWildcardCount - $bWildcardCount;
    }

    /**
     * Clear the match cache
     */
    public function clearCache(): void
    {
        $this->matchCache = [];
    }

    /**
     * Get cache statistics
     *
     * @return array{compiled_patterns: int, cached_matches: int, cache_size_bytes: int}
     */
    public function getCacheStats(): array
    {
        return [
            'compiled_patterns' => count($this->compiledPatterns),
            'cached_matches' => count($this->matchCache),
            'cache_size_bytes' => strlen(serialize($this->matchCache)),
        ];
    }
}
