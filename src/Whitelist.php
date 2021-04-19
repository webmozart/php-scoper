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
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function trim;

final class Whitelist implements Countable
{
    private $original;
    private $symbols;
    private $constants;
    private $namespaces;
    private $patterns;
    private $namespaceWhitelistIsInverted;

    private $whitelistGlobalConstants;
    private $whitelistGlobalClasses;
    private $whitelistGlobalFunctions;

    private $whitelistedFunctions = [];
    private $whitelistedClasses = [];

    public static function create(
        bool $whitelistGlobalConstants,
        bool $whitelistGlobalClasses,
        bool $whitelistGlobalFunctions,
        string ...$elements
    ): self {
        $symbols = [];
        $constants = [];
        $namespaces = [];
        $patterns = [];
        $original = [];

        foreach ($elements as $element) {
            if (isset($element[0]) && '\\' === $element[0]) {
                $element = substr($element, 1);
            }

            if ('' === trim($element)) {
                throw new InvalidArgumentException(sprintf('Invalid whitelist element "%s": cannot accept an empty string', $element));
            }

            $original[] = $element;

            if ('\*' === substr($element, -2)) {
                $namespaces[] = strtolower(substr($element, 0, -2));
            } elseif ('*' === $element) {
                $namespaces[] = '';
            } elseif (false !== strpos($element, '*')) {
                self::assertValidPattern($element);

                $patterns[] = sprintf(
                    '/^%s$/u',
                    str_replace(
                        '\\',
                        '\\\\',
                        str_replace(
                            '*',
                            '.*',
                            $element
                        )
                    )
                );
            } else {
                $symbols[] = strtolower($element);
                $constants[] = self::lowerConstantName($element);
            }
        }

        return new self(
            $whitelistGlobalConstants,
            $whitelistGlobalClasses,
            $whitelistGlobalFunctions,
            array_unique($original),
            array_flip($symbols),
            array_flip($constants),
            array_unique($patterns),
            array_unique($namespaces)
        );
    }

    private static function assertValidPattern(string $element): void
    {
        if (1 !== preg_match('/^(([\p{L}_]+\\\\)+)?[\p{L}_]*\*$/u', $element)) {
            throw new InvalidArgumentException(sprintf('Invalid whitelist pattern "%s".', $element));
        }
    }

    /**
     * @param string[] $original
     * @param string[] $patterns
     * @param string[] $namespaces
     */
    private function __construct(
        bool $whitelistGlobalConstants,
        bool $whitelistGlobalClasses,
        bool $whitelistGlobalFunctions,
        array $original,
        array $symbols,
        array $constants,
        array $patterns,
        array $namespaces
    ) {
        $this->whitelistGlobalConstants = $whitelistGlobalConstants;
        $this->whitelistGlobalClasses = $whitelistGlobalClasses;
        $this->whitelistGlobalFunctions = $whitelistGlobalFunctions;
        $this->original = $original;
        $this->symbols = $symbols;
        $this->constants = $constants;
        $this->namespaces = $namespaces;
        $this->patterns = $patterns;
        $this->namespaceWhitelistIsInverted = false;
    }

    public function belongsToWhitelistedNamespace(string $name): bool
    {
        return $this->namespaceWhitelistIsInverted ?
            !$this->belongsToWhitelistedNamespaceCore($name) :
            $this->belongsToWhitelistedNamespaceCore($name);
    }

    private function belongsToWhitelistedNamespaceCore(string $name): bool
    {
        $nameNamespace = $this->retrieveNameNamespace($name);

        foreach ($this->namespaces as $namespace) {
            if ('' === $namespace || 0 === strpos($nameNamespace, $namespace)) {
                return true;
            }
        }

        return false;
    }

    public function isWhitelistedNamespace(string $name): bool
    {
        return $this->namespaceWhitelistIsInverted ?
            !$this->isWhitelistedNamespaceCore($name) :
            $this->isWhitelistedNamespaceCore($name);
    }

    private function isWhitelistedNamespaceCore(string $name): bool
    {
        $name = strtolower($name);

        if (0 === strpos($name, '\\')) {
            $name = substr($name, 1);
        }

        foreach ($this->namespaces as $namespace) {
            if ('' === $namespace) {
                return true;
            }

            if ('' !== $namespace && 0 !== strpos($name, $namespace)) {
                continue;
            }

            $nameParts = explode('\\', $name);

            foreach (explode('\\', $namespace) as $index => $namespacePart) {
                if ($nameParts[$index] !== $namespacePart) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @internal
     */
    public function whitelistGlobalFunctions(): bool
    {
        return $this->whitelistGlobalFunctions;
    }

    public function isGlobalWhitelistedFunction(string $functionName): bool
    {
        return $this->whitelistGlobalFunctions && false === strpos($functionName, '\\');
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
    public function whitelistGlobalConstants(): bool
    {
        return $this->whitelistGlobalConstants;
    }

    public function isGlobalWhitelistedConstant(string $constantName): bool
    {
        return $this->whitelistGlobalConstants && false === strpos($constantName, '\\');
    }

    /**
     * @internal
     */
    public function whitelistGlobalClasses(): bool
    {
        return $this->whitelistGlobalClasses;
    }

    public function isGlobalWhitelistedClass(string $className): bool
    {
        return $this->whitelistGlobalClasses && false === strpos($className, '\\');
    }

    public function recordWhitelistedClass(FullyQualified $original, FullyQualified $alias): void
    {
        $this->whitelistedClasses[(string) $original] = [(string) $original, (string) $alias];
    }

    public function getRecordedWhitelistedClasses(): array
    {
        return array_values($this->whitelistedClasses);
    }

    public function isNamespaceWhitelistInverted(): bool
    {
        return $this->namespaceWhitelistIsInverted;
    }

    public function setNamespaceWhitelistIsInverted(bool $value): void
    {
        $this->namespaceWhitelistIsInverted = $value;
    }

    /**
     * Tells if a given symbol is whitelisted. Note however that it does not account for when:.
     *
     * - The symbol belongs to the global namespace and the symbols of the global namespace of this type are whitelisted
     * - Belongs to a whitelisted namespace
     *
     * @param bool $constant Unlike other symbols, constants _can_ be case insensitive but 99% are not so we leave out
     *                       the case where they are not case sensitive.
     */
    public function isSymbolWhitelisted(string $name, bool $constant = false): bool
    {
        if (false === $constant && array_key_exists(strtolower($name), $this->symbols)) {
            return true;
        }

        if ($constant && array_key_exists(self::lowerConstantName($name), $this->constants)) {
            return true;
        }

        foreach ($this->patterns as $pattern) {
            $pattern = false === $constant ? $pattern.'i' : $pattern;

            if (1 === preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     *
     * @deprecated To be replaced by getWhitelistedClasses
     */
    public function getClassWhitelistArray(): array
    {
        return array_filter(
            $this->original,
            static function (string $name): bool {
                return '*' !== $name && '\*' !== substr($name, -2);
            }
        );
    }

    public function toArray(): array
    {
        return $this->original;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->whitelistedFunctions) + count($this->whitelistedClasses);
    }

    /**
     * Transforms the constant FQ name "Acme\Foo\X" to "acme\foo\X" since the namespace remains case insensitive for
     * constants regardless of whether or not constants actually are case insensitive.
     */
    private static function lowerConstantName(string $name): string
    {
        $parts = explode('\\', $name);

        $lastPart = array_pop($parts);

        $parts = array_map('strtolower', $parts);

        $parts[] = $lastPart;

        return implode('\\', $parts);
    }

    private function retrieveNameNamespace(string $name): string
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
