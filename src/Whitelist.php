<?php

declare(strict_types=1);

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Humbug\PhpScoper;

use Countable;
use InvalidArgumentException;
use PhpParser\Node\Name\FullyQualified;
use function array_key_exists;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function ltrim;
use function preg_match as native_preg_match;
use function Safe\array_flip;
use function Safe\preg_match;
use function Safe\sprintf;
use function Safe\substr;
use function str_replace;
use function strpos;
use function strtolower;
use function trim;

final class Whitelist implements Countable
{
    /**
     * @var list<string>
     */
    private array $originalElements;

    /**
     * @var array<string, mixed>
     */
    private array $exposedSymbols;

    /**
     * @var array<string, mixed>
     */
    private array $exposedConstants;

    /**
     * @var list<string>
     */
    private array $excludedNamespaceRegexes;

    /**
     * @var list<string>
     */
    private array $excludedNamespaceNames;

    /**
     * @var list<string>
     */
    private array $exposedSymbolsPatterns;

    private bool $exposeGlobalConstants;
    private bool $exposeGlobalClasses;
    private bool $exposeGlobalFunctions;

    private array $whitelistedFunctions = [];
    private array $whitelistedClasses = [];

    /**
     * @param string[] $excludedNamespaceRegexes
     * @param string[] $excludedNamespaceNames
     */
    public static function create(
        bool $exposeGlobalConstants = false,
        bool $exposeGlobalClasses = false,
        bool $exposeGlobalFunctions = false,
        array $excludedNamespaceRegexes = [],
        array $excludedNamespaceNames = [],
        string ...$exposedElements
    ): self {
        $exposedSymbols = [];
        $exposedConstants = [];
        $exposedSymbolsPatterns = [];
        $originalElements = [];

        foreach ($exposedElements as $element) {
            $element = ltrim(trim($element), '\\');

            self::assertValidElement($element);

            $originalElements[] = $element;

            if ('\*' === substr($element, -2)) {
                $excludedNamespaceNames[] = strtolower(substr($element, 0, -2));
            } elseif ('*' === $element) {
                $excludedNamespaceNames[] = '';
            } elseif (false !== strpos($element, '*')) {
                $exposedSymbolsPatterns[] = self::createExposePattern($element);
            } else {
                $exposedSymbols[] = strtolower($element);
                $exposedConstants[] = self::lowerCaseConstantName($element);
            }
        }

        return new self(
            $exposeGlobalConstants,
            $exposeGlobalClasses,
            $exposeGlobalFunctions,
            array_unique($originalElements),
            array_flip($exposedSymbols),
            array_flip($exposedConstants),
            array_unique($exposedSymbolsPatterns),
            array_unique($excludedNamespaceNames),
            array_unique($excludedNamespaceRegexes),
        );
    }

    /**
     * @param list<string>       $originalElements
     * @param array<string, int> $exposedSymbols
     * @param array<string, int> $exposedConstants
     * @param list<string>       $exposedSymbolsPatterns
     * @param list<string>       $excludedNamespaceNames
     * @param list<string>       $excludedNamespaceRegexes
     */
    public function __construct(
        bool $exposeGlobalConstants,
        bool $exposeGlobalClasses,
        bool $exposeGlobalFunctions,
        array $originalElements,
        array $exposedSymbols,
        array $exposedConstants,
        array $exposedSymbolsPatterns,
        array $excludedNamespaceNames,
        array $excludedNamespaceRegexes
    ) {
        $this->exposeGlobalConstants = $exposeGlobalConstants;
        $this->exposeGlobalClasses = $exposeGlobalClasses;
        $this->exposeGlobalFunctions = $exposeGlobalFunctions;
        $this->originalElements = $originalElements;
        $this->exposedSymbols = $exposedSymbols;
        $this->exposedConstants = $exposedConstants;
        $this->excludedNamespaceNames = $excludedNamespaceNames;
        $this->exposedSymbolsPatterns = $exposedSymbolsPatterns;
        $this->excludedNamespaceRegexes = $excludedNamespaceRegexes;
    }

    public function belongsToExcludedNamespace(string $name): bool
    {
        return $this->isExcludedNamespace(
            $this->extractNameNamespace($name),
        );
    }

    public function isExcludedNamespace(string $name): bool
    {
        $name = strtolower(ltrim($name, '\\'));

        foreach ($this->excludedNamespaceNames as $excludedNamespaceName) {
            if ('' === $excludedNamespaceName) {
                return true;
            }

            if ('' !== $excludedNamespaceName
                && 0 !== strpos($name, $excludedNamespaceName)
            ) {
                continue;
            }

            $nameParts = explode('\\', $name);

            foreach (explode('\\', $excludedNamespaceName) as $index => $excludedNamespacePart) {
                if ($nameParts[$index] !== $excludedNamespacePart) {
                    return false;
                }
            }

            return true;
        }

        foreach ($this->excludedNamespaceRegexes as $excludedNamespace) {
            if (preg_match($excludedNamespace, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @internal
     */
    public function exposeGlobalFunctions(): bool
    {
        return $this->exposeGlobalFunctions;
    }

    public function isExposedFunctionFromGlobalNamespace(string $functionName): bool
    {
        return $this->exposeGlobalFunctions && !strpos($functionName, '\\');
    }

    public function recordWhitelistedFunction(FullyQualified $original, FullyQualified $alias): void
    {
        $this->whitelistedFunctions[(string) $original] = [(string) $original, (string) $alias];
    }

    public function getRecordedWhitelistedFunctions(): array
    {
        return array_values($this->whitelistedFunctions);
    }

    /**
     * @internal
     */
    public function exposeGlobalConstants(): bool
    {
        return $this->exposeGlobalConstants;
    }

    public function isExposedConstantFromGlobalNamespace(string $constantName): bool
    {
        return $this->exposeGlobalConstants && !strpos($constantName, '\\');
    }

    /**
     * @internal
     */
    public function exposeGlobalClasses(): bool
    {
        return $this->exposeGlobalClasses;
    }

    public function isExposedClassFromGlobalNamespace(string $className): bool
    {
        return $this->exposeGlobalClasses && !strpos($className, '\\');
    }

    public function recordWhitelistedClass(FullyQualified $original, FullyQualified $alias): void
    {
        $this->whitelistedClasses[(string) $original] = [(string) $original, (string) $alias];
    }

    public function getRecordedWhitelistedClasses(): array
    {
        return array_values($this->whitelistedClasses);
    }

    /**
     * Tells if a given symbol is exposed. Note however that it does not account for when:
     *
     * - The symbol belongs to the global namespace and the symbols of the global namespace of this type are exposed
     * - Belongs to an excluded namespace
     *
     * @param bool $constant Unlike other symbols, constants _can_ be case insensitive but 99% are not so we leave out
     *                       the case where they are not case sensitive.
     */
    public function isSymbolExposed(string $name, bool $constant = false): bool
    {
        if (!$constant && array_key_exists(strtolower($name), $this->exposedSymbols)) {
            return true;
        }

        if ($constant && array_key_exists(self::lowerCaseConstantName($name), $this->exposedConstants)) {
            return true;
        }

        foreach ($this->exposedSymbolsPatterns as $pattern) {
            $pattern = !$constant ? $pattern.'i' : $pattern;

            if (1 === native_preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        return $this->originalElements;
    }

    public function count(): int
    {
        return count($this->whitelistedFunctions) + count($this->whitelistedClasses);
    }

    private static function assertValidElement(string $element): void
    {
        if ('' !== $element) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Invalid whitelist element "%s": cannot accept an empty string',
                $element,
            ),
        );
    }

    private static function createExposePattern(string $element): string
    {
        self::assertValidPattern($element);

        return sprintf(
            '/^%s$/u',
            str_replace(
                '\\',
                '\\\\',
                str_replace(
                    '*',
                    '.*',
                    $element,
                ),
            ),
        );
    }

    private static function assertValidPattern(string $element): void
    {
        if (1 !== native_preg_match('/^(([\p{L}_]+\\\\)+)?[\p{L}_]*\*$/u', $element)) {
            throw new InvalidArgumentException(sprintf('Invalid whitelist pattern "%s".', $element));
        }
    }

    /**
     * Transforms the constant FQ name "Acme\Foo\X" to "acme\foo\X" since the namespace remains case insensitive for
     * constants regardless of whether or not constants actually are case insensitive.
     */
    private static function lowerCaseConstantName(string $name): string
    {
        $parts = explode('\\', $name);

        $lastPart = array_pop($parts);

        $parts = array_map('strtolower', $parts);

        $parts[] = $lastPart;

        return implode('\\', $parts);
    }

    private function extractNameNamespace(string $name): string
    {
        $name = strtolower($name);

        if (0 === strpos($name, '\\')) {
            $name = substr($name, 1);
        }

        $nameParts = explode('\\', $name);

        array_pop($nameParts);

        return [] === $nameParts ? '' : implode('\\', $nameParts);
    }
}
