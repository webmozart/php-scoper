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

use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Humbug\PhpScoper\Whitelist
 */
class WhitelistTest extends TestCase
{
    /**
     * @dataProvider provideExposedElements
     */
    public function test_it_can_be_created_from_a_list_of_strings(
        array $excludedNamespaceRegexes,
        array $excludedNamespaceNames,
        array $exposedElements,
        array $expectedOriginalElements,
        array $expectedNamespaceRegexes,
        array $expectedNamespaceNames,
        array $expectedSymbols,
        array $expectedConstants,
        array $exposedSymbolsPatterns
    ): void {
        $expected = new Whitelist(
            true,
            true,
            true,
            $expectedOriginalElements,
            $expectedSymbols,
            $expectedConstants,
            $exposedSymbolsPatterns,
            $expectedNamespaceNames,
            $expectedNamespaceRegexes,
        );

        $actual = Whitelist::create(
            true,
            true,
            true,
            $excludedNamespaceRegexes,
            $excludedNamespaceNames,
            ...$exposedElements,
        );

        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider provideGlobalConstantNames
     */
    public function test_it_can_tell_if_a_constant_is_a_whitelisted_global_constant(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isExposedConstantFromGlobalNamespace($constant);

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideGlobalClassNames
     */
    public function test_it_can_tell_if_a_class_is_a_whitelisted_global_class(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isExposedClassFromGlobalNamespace($constant);

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideGlobalFunctionNames
     */
    public function test_it_can_tell_if_a_function_is_a_whitelisted_global_function(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isExposedFunctionFromGlobalNamespace($constant);

        self::assertSame($expected, $actual);
    }

    public function test_it_can_record_whitelisted_functions(): void
    {
        $whitelist = Whitelist::create();

        $whitelist->recordWhitelistedFunction(
            new FullyQualified('Acme\Foo'),
            new FullyQualified('Humbug\Acme\Foo')
        );
        $whitelist->recordWhitelistedFunction(
            new FullyQualified('Acme\Foo'),
            new FullyQualified('Humbug\Acme\Foo')
        );
        $whitelist->recordWhitelistedFunction(
            new FullyQualified('Acme\Bar'),
            new FullyQualified('Humbug\Acme\Bar')
        );

        self::assertSame(
            [
                ['Acme\Foo', 'Humbug\Acme\Foo'],
                ['Acme\Bar', 'Humbug\Acme\Bar'],
            ],
            $whitelist->getRecordedWhitelistedFunctions()
        );
    }

    public function test_it_can_record_whitelisted_classes(): void
    {
        $whitelist = Whitelist::create();

        $whitelist->recordWhitelistedClass(
            new FullyQualified('Acme\foo'),
            new FullyQualified('Humbug\Acme\foo')
        );
        $whitelist->recordWhitelistedClass(
            new FullyQualified('Acme\foo'),
            new FullyQualified('Humbug\Acme\foo')
        );
        $whitelist->recordWhitelistedClass(
            new FullyQualified('Acme\bar'),
            new FullyQualified('Humbug\Acme\bar')
        );

        self::assertSame(
            [
                ['Acme\foo', 'Humbug\Acme\foo'],
                ['Acme\bar', 'Humbug\Acme\bar'],
            ],
            $whitelist->getRecordedWhitelistedClasses()
        );
    }

    /**
     * @dataProvider provideSymbolNames
     */
    public function test_it_can_tell_if_a_symbol_is_whitelisted(Whitelist $whitelist, string $symbol, bool $caseSensitive, bool $expected): void
    {
        $actual = $whitelist->isSymbolExposed($symbol, $caseSensitive);

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNamespacedSymbolWhitelists
     */
    public function test_it_can_tell_if_a_symbol_belongs_to_a_whitelisted_namespace(Whitelist $whitelist, string $symbol, bool $expected): void
    {
        $actual = $whitelist->belongsToExcludedNamespace($symbol);

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNamespaceWhitelists
     */
    public function test_it_can_tell_if_a_namespace_is_whitelisted(Whitelist $whitelist, string $namespace, bool $expected): void
    {
        $actual = $whitelist->isExcludedNamespace($namespace);

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideWhitelistToConvert
     */
    public function test_it_can_be_converted_back_into_an_array(Whitelist $whitelist, array $expected): void
    {
        $actual = $whitelist->toArray();

        self::assertSame($expected, $actual);
    }

    public static function provideExposedElements(): iterable
    {
        yield 'no elements' => [[], [], [], [], [], [], [], [], []];

        yield 'nominal class' => [
            [],
            [],
            ['Acme\Foo'],
            ['Acme\Foo'],
            [],
            [],
            ['acme\foo' => 0],
            ['acme\Foo' => 0],
            [],
        ];

        yield 'incorrect class' => [
            [],
            [],
            ['\Acme\Foo'],
            ['Acme\Foo'],
            [],
            [],
            ['acme\foo' => 0],
            ['acme\Foo' => 0],
            [],
        ];

        yield 'legacy excluded namespace (pattern)' => [
            [],
            [],
            ['Acme\Foo\*'],
            ['Acme\Foo\*'],
            [],
            ['acme\foo'],
            [],
            [],
            [],
        ];

        yield 'incorrect legacy excluded namespace (pattern)' => [
            [],
            [],
            ['\Acme\Foo\*'],
            ['Acme\Foo\*'],
            [],
            ['acme\foo'],
            [],
            [],
            [],
        ];

        yield 'nominal global namespace exclude' => [
            [],
            [],
            ['*'],
            ['*'],
            [],
            [''],
            [],
            [],
            [],
        ];

        yield 'incorrect global namespace exclude' => [
            [],
            [],
            ['\*'],
            ['*'],
            [],
            [''],
            [],
            [],
            [],
        ];

        yield 'excluded namespace (pattern)' => [
            ['~PHPUnit\Runner~'],
            [],
            [],
            [],
            ['~PHPUnit\Runner~'],
            [],
            [],
            [],
            [],
        ];

        yield 'excluded namespace (name)' => [
            [],
            ['PHPUnit\Runner'],
            [],
            [],
            [],
            ['PHPUnit\Runner'],
            [],
            [],
            [],
        ];

        yield 'nominal' => [
            [],
            [],
            ['Acme\Foo', 'Acme\Foo\*', '\*'],
            ['Acme\Foo', 'Acme\Foo\*', '*'],
            [],
            ['acme\foo', ''],
            ['acme\foo' => 0],
            ['acme\Foo' => 0],
            [],
        ];
    }

    public static function provideGlobalConstantNames(): iterable
    {
        foreach ([true, false] as $exposeGlobalClasses) {
            foreach ([true, false] as $exposeGlobalFunctions) {
                yield [
                    Whitelist::create(
                        true,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'PHP_SCOPER_VERSION',
                    true,
                ];

                yield [
                    Whitelist::create(
                        false,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'PHP_SCOPER_VERSION',
                    false,
                ];
                yield [
                    Whitelist::create(
                        true,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'PHP_SCOPER_VERSION',
                    ),
                    'PHP_SCOPER_VERSION',
                    true,
                ];

                yield [
                    Whitelist::create(
                        false,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'PHP_SCOPER_VERSION',
                    ),
                    'PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(
                        true,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(
                        false,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(
                        true,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Humbug\PHP_SCOPER_VERSION',
                    ),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(
                        false,
                        $exposeGlobalClasses,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Humbug\PHP_SCOPER_VERSION',
                    ),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];
            }
        }
    }

    public static function provideGlobalClassNames(): iterable
    {
        foreach ([true, false] as $exposeGlobalConstants) {
            foreach ([true, false] as $exposeGlobalFunctions) {
                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        true,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Foo',
                    true,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        false,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Foo',
                    false,
                ];
                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        true,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Foo',
                    ),
                    'Foo',
                    true,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        false,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Foo',
                    ),
                    'Foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        true,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        false,
                        $exposeGlobalFunctions,
                        [],
                        [],
                    ),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        true,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Acme\Foo',
                    ),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        false,
                        $exposeGlobalFunctions,
                        [],
                        [],
                        'Acme\Foo',
                    ),
                    'Acme\Foo',
                    false,
                ];
            }
        }
    }

    public static function provideGlobalFunctionNames(): iterable
    {
        foreach ([true, false] as $exposeGlobalConstants) {
            foreach ([true, false] as $exposeGlobalClasses) {
                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        true,
                        [],
                        [],
                    ),
                    'foo',
                    true,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        false,
                        [],
                        [],
                    ),
                    'foo',
                    false,
                ];
                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        true,
                        [],
                        [],
                        'foo',
                    ),
                    'foo',
                    true,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        false,
                        [],
                        [],
                        'foo',
                    ),
                    'foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        true,
                        [],
                        [],
                    ),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        false,
                        [],
                        [],
                    ),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        true,
                        [],
                        [],
                        'Acme\foo',
                    ),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create(
                        $exposeGlobalConstants,
                        $exposeGlobalClasses,
                        false,
                        [],
                        [],
                        'Acme\foo',
                    ),
                    'Acme\foo',
                    false,
                ];
            }
        }
    }

    public static function provideSymbolNames(): iterable
    {
        yield [
            Whitelist::create(),
            'Acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            'Acme\Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            'acme\foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            'acme\foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            'Acme\Foo\Bar',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
            ),
            'Acme',
            true,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
            ),
            'Acme',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
            ),
            'acme',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
            ),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme\Foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme\Foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'Acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'Acme\Foo',
            true,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'Acme\Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'acme\foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\F*',
            ),
            'acme\foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'Acme_Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'Acme_Foo_Bar',
            false,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'acme_foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'Acme_Foo',
            true,
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'acme_foo_bar',
            true,
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme_Foo*',
            ),
            'Acme_Foo_bar',
            true,
            true,
        ];
    }

    public static function provideNamespacedSymbolWhitelists(): iterable
    {
        yield [
            Whitelist::create(),
            'Acme\Foo',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'Acme\Foo',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'acme\foo',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme\Foo',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme_Foo',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme\foo',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme_foo',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'Acme\Foo\Bar',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'acme\foo\bar',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\B*',
            ),
            'Acme\Foo\Bar',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\B*',
            ),
            'Acme\Foo\B\Bar',
            false,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme\Foo',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme\foo',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme_Foo',
            true,
        ];

        yield [

            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme_foo',
            true,
        ];
    }

    public static function provideNamespaceWhitelists(): iterable
    {
        yield [
            Whitelist::create(),
            'Acme\Foo',
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'Acme_Foo',
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            'acme_foo',
            false,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'Acme\Foo\Bar',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            'acme\foo\bar',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'Acme_Foo',
            true,
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            'acme_foo',
            true,
        ];
    }

    public static function provideWhitelistToConvert(): iterable
    {
        yield [
            Whitelist::create(),
            [],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            ['Acme\Foo'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\Acme\Foo',
            ),
            ['Acme\Foo'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*',
            ),
            ['Acme\Foo\*'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\Acme\Foo\*',
            ),
            ['Acme\Foo\*'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '*',
            ),
            ['*'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                '\*',
            ),
            ['*'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
                'Acme\Foo',
                'Acme\Foo\*',
                '*',
            ),
            ['Acme', 'Acme\Foo', 'Acme\Foo\*', '*'],
        ];

        yield [
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme',
                'Acme',
            ),
            ['Acme'],
        ];
    }
}
