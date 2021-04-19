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

use Generator;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function array_flip;

/**
 * @covers \Humbug\PhpScoper\Whitelist
 */
class WhitelistTest extends TestCase
{
    /**
     * @dataProvider provideWhitelists
     */
    public function test_it_can_be_created_from_a_list_of_strings(
        array $whitelist,
        array $expectedNamespaces,
        array $expectedSymbols,
        array $expectedConstants
    ): void {
        $whitelistObject = Whitelist::create(true, true, true, ...$whitelist);

        $whitelistReflection = new ReflectionClass(Whitelist::class);

        $whitelistSymbolReflection = $whitelistReflection->getProperty('symbols');
        $whitelistSymbolReflection->setAccessible(true);
        $actualSymbols = $whitelistSymbolReflection->getValue($whitelistObject);

        $whitelistNamespaceReflection = $whitelistReflection->getProperty('namespaces');
        $whitelistNamespaceReflection->setAccessible(true);
        $actualNamespaces = $whitelistNamespaceReflection->getValue($whitelistObject);

        $whitelistConstantReflection = $whitelistReflection->getProperty('constants');
        $whitelistConstantReflection->setAccessible(true);
        $actualConstants = $whitelistConstantReflection->getValue($whitelistObject);

        $this->assertTrue($whitelistObject->whitelistGlobalConstants());
        $this->assertTrue($whitelistObject->whitelistGlobalClasses());
        $this->assertTrue($whitelistObject->whitelistGlobalFunctions());
        $this->assertSame($expectedNamespaces, $actualNamespaces);
        $this->assertSame($expectedSymbols, array_flip($actualSymbols));
        $this->assertSame($expectedConstants, array_flip($actualConstants));

        $whitelistObject = Whitelist::create(false, false, false, ...$whitelist);

        $this->assertFalse($whitelistObject->whitelistGlobalConstants());
        $this->assertFalse($whitelistObject->whitelistGlobalClasses());
        $this->assertFalse($whitelistObject->whitelistGlobalFunctions());
        $this->assertSame($expectedNamespaces, $actualNamespaces);
        $this->assertSame($expectedSymbols, array_flip($actualSymbols));
        $this->assertSame($expectedConstants, array_flip($actualConstants));
    }

    /**
     * @dataProvider provideGlobalConstantNames
     */
    public function test_it_can_tell_if_a_constant_is_a_whitelisted_global_constant(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isGlobalWhitelistedConstant($constant);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideGlobalClassNames
     */
    public function test_it_can_tell_if_a_class_is_a_whitelisted_global_class(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isGlobalWhitelistedClass($constant);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideGlobalFunctionNames
     */
    public function test_it_can_tell_if_a_function_is_a_whitelisted_global_function(Whitelist $whitelist, string $constant, bool $expected): void
    {
        $actual = $whitelist->isGlobalWhitelistedFunction($constant);

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_record_whitelisted_functions(): void
    {
        $whitelist = Whitelist::create(true, true, true);

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

        $this->assertSame(
            [
                ['Acme\Foo', 'Humbug\Acme\Foo'],
                ['Acme\Bar', 'Humbug\Acme\Bar'],
            ],
            $whitelist->getRecordedWhitelistedFunctions()
        );
    }

    public function test_it_can_record_whitelisted_classes(): void
    {
        $whitelist = Whitelist::create(true, true, true);

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

        $this->assertSame(
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
        $actual = $whitelist->isSymbolWhitelisted($symbol, $caseSensitive);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNamespacedSymbolWhitelists
     */
    public function test_it_can_tell_if_a_symbol_belongs_to_a_whitelisted_namespace(Whitelist $whitelist, string $symbol, bool $expected): void
    {
        $actual = $whitelist->belongsToWhitelistedNamespace($symbol);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNamespacedSymbolWhitelists
     */
    public function test_it_can_tell_if_a_symbol_belongs_to_a_whitelisted_namespace_in_inclusive_mode(Whitelist $whitelist, string $symbol, bool $expected): void
    {
        $whitelist->setNamespaceWhitelistIsInverted(true);
        $actual = $whitelist->belongsToWhitelistedNamespace($symbol);

        $this->assertSame(!$expected, $actual);
    }

    /**
     * @dataProvider provideNamespaceWhitelists
     */
    public function test_it_can_tell_if_a_namespace_is_whitelisted(Whitelist $whitelist, string $namespace, bool $expected): void
    {
        $actual = $whitelist->isWhitelistedNamespace($namespace);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideNamespaceWhitelists
     */
    public function test_it_can_tell_if_a_namespace_is_whitelisted_in_inverted_mode(Whitelist $whitelist, string $namespace, bool $expected): void
    {
        $whitelist->setNamespaceWhitelistIsInverted(true);
        $actual = $whitelist->isWhitelistedNamespace($namespace);

        $this->assertSame(!$expected, $actual);
    }

    /**
     * @dataProvider provideWhitelistToConvert
     */
    public function test_it_can_be_converted_back_into_an_array(Whitelist $whitelist, array $expected): void
    {
        $actual = $whitelist->toArray();

        $this->assertSame($expected, $actual);
    }

    public function provideWhitelists(): Generator
    {
        yield [[], [], [], []];

        yield [['Acme\Foo'], [], ['acme\foo'], ['acme\Foo']];

        yield [['Acme\Foo\*'], ['acme\foo'], [], []];

        yield [['\*'], [''], [], []];

        yield [['*'], [''], [], []];

        yield [
            ['Acme\Foo', 'Acme\Foo\*', '\*'],
            ['acme\foo', ''],
            ['acme\foo'],
            ['acme\Foo'],
        ];
    }

    public function provideGlobalConstantNames(): Generator
    {
        foreach ([true, false] as $whitelistGlobalClasses) {
            foreach ([true, false] as $whitelistGlobalFunctions) {
                yield [
                    Whitelist::create(true, $whitelistGlobalClasses, $whitelistGlobalFunctions),
                    'PHP_SCOPER_VERSION',
                    true,
                ];

                yield [
                    Whitelist::create(false, $whitelistGlobalClasses, $whitelistGlobalFunctions),
                    'PHP_SCOPER_VERSION',
                    false,
                ];
                yield [
                    Whitelist::create(true, $whitelistGlobalClasses, $whitelistGlobalFunctions, 'PHP_SCOPER_VERSION'),
                    'PHP_SCOPER_VERSION',
                    true,
                ];

                yield [
                    Whitelist::create(false, $whitelistGlobalClasses, $whitelistGlobalFunctions, 'PHP_SCOPER_VERSION'),
                    'PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(true, $whitelistGlobalClasses, $whitelistGlobalFunctions),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(false, $whitelistGlobalClasses, $whitelistGlobalFunctions),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(true, $whitelistGlobalClasses, $whitelistGlobalFunctions, 'Humbug\PHP_SCOPER_VERSION'),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];

                yield [
                    Whitelist::create(false, $whitelistGlobalClasses, $whitelistGlobalFunctions, 'Humbug\PHP_SCOPER_VERSION'),
                    'Humbug\PHP_SCOPER_VERSION',
                    false,
                ];
            }
        }
    }

    public function provideGlobalClassNames(): Generator
    {
        foreach ([true, false] as $whitelistGlobalConstants) {
            foreach ([true, false] as $whitelistGlobalFunctions) {
                yield [
                    Whitelist::create($whitelistGlobalConstants, true, $whitelistGlobalFunctions),
                    'Foo',
                    true,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, false, $whitelistGlobalFunctions),
                    'Foo',
                    false,
                ];
                yield [
                    Whitelist::create($whitelistGlobalConstants, true, $whitelistGlobalFunctions, 'Foo'),
                    'Foo',
                    true,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, false, $whitelistGlobalFunctions, 'Foo'),
                    'Foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, true, $whitelistGlobalFunctions),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, false, $whitelistGlobalFunctions),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, true, $whitelistGlobalFunctions, 'Acme\Foo'),
                    'Acme\Foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, false, $whitelistGlobalFunctions, 'Acme\Foo'),
                    'Acme\Foo',
                    false,
                ];
            }
        }
    }

    public function provideGlobalFunctionNames(): Generator
    {
        foreach ([true, false] as $whitelistGlobalConstants) {
            foreach ([true, false] as $whitelistGlobalClasses) {
                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, true),
                    'foo',
                    true,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, false),
                    'foo',
                    false,
                ];
                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, true, 'foo'),
                    'foo',
                    true,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, false, 'foo'),
                    'foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, true),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, false),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, true, 'Acme\foo'),
                    'Acme\foo',
                    false,
                ];

                yield [
                    Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, false, 'Acme\foo'),
                    'Acme\foo',
                    false,
                ];
            }
        }
    }

    public function provideSymbolNames(): Generator
    {
        yield [
            Whitelist::create(true, true, true),
            'Acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            'Acme\Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            'acme\foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            'acme\foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            'Acme\Foo\Bar',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme'),
            'Acme',
            true,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme'),
            'Acme',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme'),
            'acme',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme'),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme\Foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme\Foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme\Foo',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'Acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'Acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'acme',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'acme',
            false,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'Acme\Foo',
            true,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'Acme\Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'acme\foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\F*'),
            'acme\foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'Acme_Foo',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'Acme_Foo_Bar',
            false,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'acme_foo',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'Acme_Foo',
            true,
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'acme_foo_bar',
            true,
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme_Foo*'),
            'Acme_Foo_bar',
            true,
            true,
        ];
    }

    public function provideNamespacedSymbolWhitelists(): Generator
    {
        yield [
            Whitelist::create(true, true, true),
            'Acme\Foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'Acme\Foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'acme\foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme_Foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme_foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'Acme\Foo\Bar',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'acme\foo\bar',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\B*'),
            'Acme\Foo\Bar',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\B*'),
            'Acme\Foo\B\Bar',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme_Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme_foo',
            true,
        ];
    }

    public function provideNamespaceWhitelists(): Generator
    {
        yield [
            Whitelist::create(true, true, true),
            'Acme\Foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'Acme_Foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\*'),
            'acme_foo',
            false,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'Acme\Foo\Bar',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            'acme\foo\bar',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme\Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme\foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'Acme_Foo',
            true,
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            'acme_foo',
            true,
        ];
    }

    public function provideWhitelistToConvert(): Generator
    {
        yield [
            Whitelist::create(true, true, true),
            [],
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo'),
            ['Acme\Foo'],
        ];

        yield [
            Whitelist::create(true, true, true, '\Acme\Foo'),
            ['Acme\Foo'],
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme\Foo\*'),
            ['Acme\Foo\*'],
        ];

        yield [
            Whitelist::create(true, true, true, '\Acme\Foo\*'),
            ['Acme\Foo\*'],
        ];

        yield [
            Whitelist::create(true, true, true, '*'),
            ['*'],
        ];

        yield [
            Whitelist::create(true, true, true, '\*'),
            ['*'],
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme', 'Acme\Foo', 'Acme\Foo\*', '*'),
            ['Acme', 'Acme\Foo', 'Acme\Foo\*', '*'],
        ];

        yield [
            Whitelist::create(true, true, true, 'Acme', 'Acme'),
            ['Acme'],
        ];
    }
}
