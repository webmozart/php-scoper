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

namespace Humbug\PhpScoper\Scoper;

use Error;
use Humbug\PhpScoper\ConfigurationKeys;
use Humbug\PhpScoper\ConfigurationWhitelistFactory;
use Humbug\PhpScoper\PhpParser\TraverserFactory;
use Humbug\PhpScoper\Reflector;
use Humbug\PhpScoper\RegexChecker;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Whitelist;
use InvalidArgumentException;
use PhpParser\Error as PhpParserError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use UnexpectedValueException;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_slice;
use function array_values;
use function basename;
use function count;
use function current;
use function explode;
use function Humbug\PhpScoper\create_fake_patcher;
use function Humbug\PhpScoper\create_parser;
use function implode;
use function is_array;
use function is_string;
use function min;
use function Safe\preg_split;
use function Safe\sprintf;
use function Safe\usort;
use function str_repeat;
use function strlen;
use function strpos;
use const PHP_EOL;
use const PHP_VERSION_ID;

class PhpScoperSpecTest extends TestCase
{
    private const SPECS_PATH = __DIR__.'/../../specs';
    private const SECONDARY_SPECS_PATH = __DIR__.'/../../_specs';

    private const SPECS_META_KEYS = [
        'minPhpVersion',
        'maxPhpVersion',
        'title',
        ConfigurationKeys::PREFIX_KEYWORD,
        ConfigurationKeys::WHITELIST_KEYWORD,
        ConfigurationKeys::EXCLUDE_NAMESPACES_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD,
        ConfigurationKeys::CONSTANTS_INTERNAL_SYMBOLS_KEYWORD,
        ConfigurationKeys::CLASSES_INTERNAL_SYMBOLS_KEYWORD,
        ConfigurationKeys::FUNCTIONS_INTERNAL_SYMBOLS_KEYWORD,
        'registered-classes',
        'registered-functions',
    ];

    private const SPECS_SPEC_KEYS = [
        ConfigurationKeys::PREFIX_KEYWORD,
        ConfigurationKeys::WHITELIST_KEYWORD,
        ConfigurationKeys::EXCLUDE_NAMESPACES_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD,
        ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD,
        ConfigurationKeys::CONSTANTS_INTERNAL_SYMBOLS_KEYWORD,
        ConfigurationKeys::CLASSES_INTERNAL_SYMBOLS_KEYWORD,
        ConfigurationKeys::FUNCTIONS_INTERNAL_SYMBOLS_KEYWORD,
        'registered-classes',
        'registered-functions',
        'payload',
    ];

    /**
     * This test is to ensure no file is left in _specs for the CI. It is fine otherwise for this test to fail locally
     * when developing something.
     */
    public function test_it_uses_the_right_specs_directory(): void
    {
        $files = (new Finder())->files()->in(self::SECONDARY_SPECS_PATH);

        self::assertCount(0, $files);
    }

    /**
     * @dataProvider provideValidFiles
     */
    public function test_can_scope_valid_files(
        string $file,
        string $spec,
        string $contents,
        string $prefix,
        Whitelist $whitelist,
        array $internalClasses,
        array $internalFunctions,
        array $internalConstants,
        ?string $expected,
        array $expectedRegisteredClasses,
        array $expectedRegisteredFunctions,
        ?int $minPhpVersion,
        ?int $maxPhpVersion
    ): void {
        if (null !== $minPhpVersion && $minPhpVersion > PHP_VERSION_ID) {
            self::markTestSkipped(sprintf('Min PHP version not matched for spec %s', $spec));
        }

        if (null !== $maxPhpVersion && $maxPhpVersion <= PHP_VERSION_ID) {
            self::markTestSkipped(sprintf('Max PHP version not matched for spec %s', $spec));
        }

        $filePath = 'file.php';
        $patchers = [create_fake_patcher()];

        $scoper = $this->createScoper(
            $internalClasses,
            $internalFunctions,
            $internalConstants,
        );

        try {
            $actual = $scoper->scope($filePath, $contents, $prefix, $patchers, $whitelist);

            if (null === $expected) {
                self::fail('Expected exception to be thrown.');
            }
        } catch (UnexpectedValueException $exception) {
            if (null !== $expected) {
                throw $exception;
            }

            self::assertTrue(true);

            return;
        } catch (PhpParserError $error) {
            if (0 !== strpos($error->getMessage(), 'Syntax error,')) {
                throw new Error(
                    sprintf(
                        'Could not parse the spec %s: %s',
                        $spec,
                        $error->getMessage()
                    ),
                    0,
                    $error
                );
            }

            $lines = array_values(array_filter(explode("\n", $contents)));

            $startLine = $error->getAttributes()['startLine'] - 1;
            $endLine = $error->getAttributes()['endLine'] + 1;

            self::fail(
                sprintf(
                    'Unexpected parse error found in the following lines: %s%s%s',
                    $error->getMessage(),
                    "\n\n> ",
                    implode(
                        "\n> ",
                        array_slice($lines, $startLine, $endLine - $startLine + 1)
                    )
                )
            );
        } catch (Throwable $throwable) {
            throw new Error(
                sprintf(
                    'Could not parse the spec %s: %s',
                    $spec,
                    $throwable->getMessage()
                ),
                0,
                $throwable
            );
        }

        $specMessage = $this->createSpecMessage(
            $file,
            $spec,
            $contents,
            $whitelist,
            $expected,
            $actual,
            $expectedRegisteredClasses,
            $expectedRegisteredFunctions
        );

        self::assertSame($expected, $actual, $specMessage);

        $actualRecordedWhitelistedClasses = $whitelist->getRecordedWhitelistedClasses();

        $this->assertSameRecordedSymbols($expectedRegisteredClasses, $actualRecordedWhitelistedClasses, $specMessage);

        $actualRecordedWhitelistedFunctions = $whitelist->getRecordedWhitelistedFunctions();

        $this->assertSameRecordedSymbols($expectedRegisteredFunctions, $actualRecordedWhitelistedFunctions, $specMessage);
    }

    public static function provideValidFiles(): iterable
    {
        $sourceDir = self::SECONDARY_SPECS_PATH;

        $files = (new Finder())->files()->in($sourceDir);

        if (0 === count($files)) {
            $sourceDir = self::SPECS_PATH;

            $files = (new Finder())->files()->in($sourceDir);
        }

        $files->sortByName();

        foreach ($files as $file) {
            /* @var SplFileInfo $file */
            try {
                $fixtures = include $file;

                $meta = $fixtures['meta'];
                unset($fixtures['meta']);

                foreach ($fixtures as $fixtureTitle => $fixtureSet) {
                    yield from self::parseSpecFile(
                        basename($sourceDir).'/'.$file->getRelativePathname(),
                        $meta,
                        $fixtureTitle,
                        $fixtureSet,
                    );
                }
            } catch (Throwable $throwable) {
                self::fail(
                    sprintf(
                        'An error occurred while parsing the file "%s": %s',
                        $file,
                        $throwable->getMessage(),
                    ),
                );
            }
        }
    }

    private function createScoper(
        array $internalClasses,
        array $internalFunctions,
        array $internalConstants
    ): Scoper
    {
        $phpParser = create_parser();

        $reflector = Reflector::createWithPhpStormStubs()
            ->withSymbols(
                $internalClasses,
                $internalFunctions,
                $internalConstants,
            );

        return new PhpScoper(
            $phpParser,
            new FakeScoper(),
            new TraverserFactory($reflector),
        );
    }

    /**
     * @param string|int   $fixtureTitle
     * @param string|array $fixtureSet
     */
    private static function parseSpecFile(string $file, array $meta, $fixtureTitle, $fixtureSet): iterable
    {
        $spec = sprintf(
            '[%s] %s',
            $meta['title'],
            isset($fixtureSet['spec']) ? $fixtureSet['spec'] : $fixtureTitle
        );

        $payload = is_string($fixtureSet) ? $fixtureSet : $fixtureSet['payload'];

        $payloadParts = preg_split("/\n----(?:\n|$)/", $payload);

        self::assertSame(
            [],
            $diff = array_diff(
                array_keys($meta),
                self::SPECS_META_KEYS
            ),
            sprintf(
                'Expected the keys found in the meta section to be known keys, unknown keys: "%s"',
                implode('", "', $diff)
            )
        );

        if (is_array($fixtureSet)) {
            self::assertSame(
                [],
                $diff = array_diff(
                    array_keys($fixtureSet),
                    self::SPECS_SPEC_KEYS
                ),
                sprintf(
                    'Expected the keys found in the spec section to be known keys, unknown keys: "%s"',
                    implode('", "', $diff)
                )
            );
        }

        yield [
            $file,
            $spec,
            $payloadParts[0],   // Input
            $fixtureSet[ConfigurationKeys::PREFIX_KEYWORD] ?? $meta[ConfigurationKeys::PREFIX_KEYWORD],
            self::createWhitelist(
                $file,
                is_string($fixtureSet) ? [] : $fixtureSet,
                $meta,
            ),
            $fixtureSet[ConfigurationKeys::CLASSES_INTERNAL_SYMBOLS_KEYWORD] ?? $meta[ConfigurationKeys::CLASSES_INTERNAL_SYMBOLS_KEYWORD],
            $fixtureSet[ConfigurationKeys::FUNCTIONS_INTERNAL_SYMBOLS_KEYWORD] ?? $meta[ConfigurationKeys::FUNCTIONS_INTERNAL_SYMBOLS_KEYWORD],
            $fixtureSet[ConfigurationKeys::CONSTANTS_INTERNAL_SYMBOLS_KEYWORD] ?? $meta[ConfigurationKeys::CONSTANTS_INTERNAL_SYMBOLS_KEYWORD],
            '' === $payloadParts[1] ? null : $payloadParts[1],   // Expected output; null means an exception is expected,
            $fixtureSet['registered-classes'] ?? $meta['registered-classes'],
            $fixtureSet['registered-functions'] ?? $meta['registered-functions'],
            $meta['minPhpVersion'] ?? null,
            $meta['maxPhpVersion'] ?? null,
        ];
    }

    /**
     * @param string|array $fixtureSet
     */
    private static function createWhitelist(string $file, $fixtureSet, array $meta): Whitelist
    {
        $configKeys = [
            ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD,
            ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD,
            ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD,
            ConfigurationKeys::EXCLUDE_NAMESPACES_KEYWORD,
            ConfigurationKeys::WHITELIST_KEYWORD,
        ];

        if (is_string($fixtureSet)) {
            $fixtureSet = [];
        }

        $mergedConfig = array_merge($meta, $fixtureSet);

        $config = [];

        foreach ($configKeys as $key) {
            if (!array_key_exists($key, $mergedConfig)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Missing the key "%s" for the file "%s"',
                        $key,
                        $file,
                    ),
                );
            }

            $config[$key] = $mergedConfig[$key];
        }

        return (new ConfigurationWhitelistFactory(new RegexChecker()))->createWhitelist($config);
    }

    /**
     * @param string[][] $expectedRegisteredClasses
     * @param string[][] $expectedRegisteredFunctions
     */
    private function createSpecMessage(
        string $file,
        string $spec,
        string $contents,
        Whitelist $whitelist,
        ?string $expected,
        ?string $actual,
        array $expectedRegisteredClasses,
        array $expectedRegisteredFunctions
    ): string {
        $formattedWhitelist = $this->formatSimpleList($whitelist->toArray());

        $formattedWhitelistGlobalConstants = $this->convertBoolToString($whitelist->exposeGlobalConstants());
        $formattedWhitelistGlobalFunctions = $this->convertBoolToString($whitelist->exposeGlobalFunctions());

        $whitelist->getRecordedWhitelistedFunctions();
        $whitelist->getRecordedWhitelistedClasses();

        $formattedExpectedRegisteredClasses = $this->formatTupleList($expectedRegisteredClasses);
        $formattedExpectedRegisteredFunctions = $this->formatTupleList($expectedRegisteredFunctions);

        $formattedActualRegisteredClasses = $this->formatTupleList($whitelist->getRecordedWhitelistedClasses());
        $formattedActualRegisteredFunctions = $this->formatTupleList($whitelist->getRecordedWhitelistedFunctions());

        $titleSeparator = str_repeat(
            '=',
            min(
                strlen($spec),
                80
            )
        );

        return <<<OUTPUT
$titleSeparator
SPECIFICATION
$titleSeparator
$spec
$file

$titleSeparator
INPUT
whitelist: $formattedWhitelist
whitelist global constants: $formattedWhitelistGlobalConstants
whitelist global functions: $formattedWhitelistGlobalFunctions
$titleSeparator
$contents

$titleSeparator
EXPECTED
$titleSeparator
$expected
----------------
registered classes: $formattedExpectedRegisteredClasses
registered functions: $formattedExpectedRegisteredFunctions

$titleSeparator
ACTUAL
$titleSeparator
$actual
----------------
registered classes: $formattedActualRegisteredClasses
registered functions: $formattedActualRegisteredFunctions

-------------------------------------------------------------------------------
OUTPUT
        ;
    }

    /**
     * @param string[] $strings
     */
    private function formatSimpleList(array $strings): string
    {
        if (0 === count($strings)) {
            return '[]';
        }

        if (1 === count($strings)) {
            return '['.current($strings).']';
        }

        return sprintf(
            "[\n%s\n]",
            implode(
                PHP_EOL,
                array_map(
                    static function (string $string): string {
                        return '  - '.$string;
                    },
                    $strings
                )
            )
        );
    }

    /**
     * @param string[][] $stringTuples
     */
    private function formatTupleList(array $stringTuples): string
    {
        if (0 === count($stringTuples)) {
            return '[]';
        }

        if (1 === count($stringTuples)) {
            /** @var string[] $tuple */
            $tuple = current($stringTuples);

            return sprintf('[%s => %s]', ...$tuple);
        }

        return sprintf(
            "[\n%s\n]",
            implode(
                PHP_EOL,
                array_map(
                    static function (array $stringTuple): string {
                        return sprintf('  - %s => %s', ...$stringTuple);
                    },
                    $stringTuples
                )
            )
        );
    }

    private function convertBoolToString(bool $bool): string
    {
        return true === $bool ? 'true' : 'false';
    }

    /**
     * @param string[][] $expected
     * @param string[][] $actual
     */
    private function assertSameRecordedSymbols(array $expected, array $actual, string $message): void
    {
        $sort = static fn (array $a, array $b) => $a[0] <=> $b[0];

        usort($expected, $sort);
        usort($actual, $sort);

        self::assertSame($expected, $actual, $message);
    }
}
