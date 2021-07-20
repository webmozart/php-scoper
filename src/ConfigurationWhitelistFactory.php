<?php

declare(strict_types=1);

namespace Humbug\PhpScoper;

use InvalidArgumentException;
use function array_key_exists;
use function array_keys;
use function array_values;
use function gettype;
use function is_array;
use function is_bool;
use function is_string;
use function Safe\sprintf;

final class ConfigurationWhitelistFactory
{
    private RegexChecker $regexChecker;

    public function __construct(RegexChecker $regexChecker)
    {
        $this->regexChecker = $regexChecker;
    }

    public function createWhitelist(array $config): Whitelist
    {
        [
            $excludedNamespaceRegexes,
            $excludedNamespaceNames,
        ] = $this->retrieveExcludedNamespaces($config);

        $exposedElements = self::retrieveExposedElements($config);

        $exposeGlobalConstants = self::retrieveExposeGlobalSymbol(
            $config,
            ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD,
        );
        $exposeGlobalClasses = self::retrieveExposeGlobalSymbol(
            $config,
            ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD,
        );
        $exposeGlobalFunctions = self::retrieveExposeGlobalSymbol(
            $config,
            ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD,
        );

        return Whitelist::create(
            $exposeGlobalConstants,
            $exposeGlobalClasses,
            $exposeGlobalFunctions,
            $excludedNamespaceRegexes,
            $excludedNamespaceNames,
            ...$exposedElements,
        );
    }

    /**
     * @return array{string[], string[]}
     */
    private function retrieveExcludedNamespaces(array $config): array
    {
        $key = ConfigurationKeys::EXCLUDE_NAMESPACES_KEYWORD;

        if (!array_key_exists($key, $config)) {
            return [[], []];
        }

        $regexesAndNamespaceNames = $config[$key];

        if (!is_array($regexesAndNamespaceNames)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected "%s" to be an array of strings, got "%s" instead.',
                    $key,
                    gettype($regexesAndNamespaceNames),
                ),
            );
        }

        // Store the strings in the keys for avoiding a unique check later on
        $regexes = [];
        $namespaceNames = [];

        foreach ($regexesAndNamespaceNames as $index => $regexOrNamespaceName) {
            if (!is_string($regexOrNamespaceName)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected "%s" to be an array of strings, got "%s" for the element with the index "%s".',
                        $key,
                        gettype($regexOrNamespaceName),
                        $index,
                    ),
                );
            }

            if (!$this->regexChecker->isRegexLike($regexOrNamespaceName)) {
                $namespaceNames[$regexOrNamespaceName] = null;

                continue;
            }

            $excludeNamespaceRegex = $regexOrNamespaceName;

            $errorMessage = $this->regexChecker->validateRegex($excludeNamespaceRegex);

            if (null !== $errorMessage) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected "%s" to be an array of valid regexes. The element "%s" with the index "%s" is not: %s.',
                        $key,
                        $excludeNamespaceRegex,
                        $index,
                        $errorMessage,
                    ),
                );
            }

            // Ensure namespace comparisons are always case-insensitive
            $excludeNamespaceRegex .= 'i';
            $regexes[$excludeNamespaceRegex] = null;
        }

        return [
            array_keys($regexes),
            array_keys($namespaceNames),
        ];
    }

    /**
     * return list<string>
     */
    private static function retrieveExposedElements(array $config): array
    {
        $key = ConfigurationKeys::WHITELIST_KEYWORD;

        if (!array_key_exists($key, $config)) {
            return [];
        }

        $whitelist = $config[$key];

        if (!is_array($whitelist)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected "%s" to be an array of strings, found "%s" instead.',
                    $key,
                    gettype($whitelist),
                ),
            );
        }

        foreach ($whitelist as $index => $className) {
            if (is_string($className)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected whitelist to be an array of string, the "%d" element is not.',
                    $index,
                ),
            );
        }

        return array_values($whitelist);
    }

    private static function retrieveExposeGlobalSymbol(array $config, string $key): bool
    {
        if (!array_key_exists($key, $config)) {
            return false;
        }

        $value = $config[$key];

        if (!is_bool($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected %s to be a boolean, found "%s" instead.',
                    $key,
                    gettype($value),
                ),
            );
        }

        return $value;
    }
}
