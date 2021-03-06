<?php

declare(strict_types=1);

namespace Humbug\PhpScoper;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Humbug\PhpScoper\ConfigurationWhitelistFactory
 */
final class ConfigurationWhitelistFactoryTest extends TestCase
{
    private ConfigurationWhitelistFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ConfigurationWhitelistFactory();
    }

    /**
     * @dataProvider configProvider
     */
    public function test_it_can_create_a_whitelist_object_from_the_config(
        array $config,
        Whitelist $expected
    ): void
    {
        $actual = $this->factory->createWhitelist($config);

        self::assertEquals($expected, $actual);
    }

    public static function configProvider(): iterable
    {
        yield 'empty config' => [
            [],
            Whitelist::create(
                false,
                false,
                false,
            ),
        ];

        yield 'expose global constants' => [
            [
                ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD => true,
            ],
            Whitelist::create(
                true,
                false,
                false,
            ),
        ];

        yield 'expose global classes' => [
            [
                ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD => true,
            ],
            Whitelist::create(
                false,
                true,
                false,
            ),
        ];

        yield 'expose global functions' => [
            [
                ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD => true,
            ],
            Whitelist::create(
                false,
                false,
                true,
            ),
        ];

        yield 'expose element' => [
            [
                ConfigurationKeys::WHITELIST_KEYWORD => [
                    'Acme\Foo',
                ],
            ],
            Whitelist::create(
                false,
                false,
                false,
                'Acme\Foo',
            ),
        ];

        yield 'legacy expose namespace' => [
            [
                ConfigurationKeys::WHITELIST_KEYWORD => [
                    'PHPUnit\Runner\*',
                ],
            ],
            Whitelist::create(
                false,
                false,
                false,
                'PHPUnit\Runner\*',
            ),
        ];

        yield 'nominal' => [
            [
                ConfigurationKeys::EXPOSE_GLOBAL_CONSTANTS_KEYWORD => true,
                ConfigurationKeys::EXPOSE_GLOBAL_CLASSES_KEYWORD => true,
                ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD => true,
                ConfigurationKeys::WHITELIST_KEYWORD => [
                    'PHPUnit\Runner\*',
                    'Acme\Foo',
                ],
            ],
            Whitelist::create(
                true,
                true,
                true,
                'PHPUnit\Runner\*',
                'Acme\Foo',
            ),
        ];
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function test_it_cannot_create_a_whitelist_from_an_invalid_config(
        array $config,
        string $expectedExceptionClassName,
        string $expectedExceptionMessage
    ): void
    {
        $this->expectException($expectedExceptionClassName);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->createWhitelist($config);
    }

    public static function invalidConfigProvider(): iterable
    {
        yield 'expose global is not a bool' => [
            [
                ConfigurationKeys::EXPOSE_GLOBAL_FUNCTIONS_KEYWORD => '',
            ],
            InvalidArgumentException::class,
            'Expected expose-global-functions to be a boolean, found "string" instead.',
        ];

        // TODO: need to find a case
        //yield 'exclude namespace contains an invalid regex-like expression' => [];

        yield 'whitelist is not an array' => [
            [
                ConfigurationKeys::WHITELIST_KEYWORD => true,
            ],
            InvalidArgumentException::class,
            'Expected "whitelist" to be an array of strings, found "boolean" instead.',
        ];

        yield 'whitelist is not an array of strings' => [
            [
                ConfigurationKeys::WHITELIST_KEYWORD => [true],
            ],
            InvalidArgumentException::class,
            'Expected whitelist to be an array of string, the "0" element is not.',
        ];
    }
}
