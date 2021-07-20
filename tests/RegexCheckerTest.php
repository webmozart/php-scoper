<?php

declare(strict_types=1);

namespace Humbug\PhpScoper;

use Humbug\PhpScoper\RegexChecker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Humbug\PhpScoper\RegexChecker
 */
final class RegexCheckerTest extends TestCase
{
    private RegexChecker $regexChecker;

    protected function setUp(): void
    {
        $this->regexChecker = new RegexChecker();
    }

    /**
     * @dataProvider regexLikeProvider
     */
    public function test_it_can_tell_if_a_string_looks_like_a_regex(
        string $value,
        bool $expected
    ): void
    {
        $actual = $this->regexChecker->isRegexLike($value);

        self::assertSame($expected, $actual);
    }

    public static function regexLikeProvider(): iterable
    {
        yield 'empty string' => [
            '',
            false,
        ];

        yield 'regular string' => [
            'foo',
            false,
        ];

        yield 'empty regex' => [
            '//',
            true,
        ];

        yield 'empty regex with flags' => [
            '//ui',
            true,
        ];

        yield 'two letters non-regex' => [
            '/~',
            false,
        ];

        yield 'regular regex' => [
            '/foo/',
            true,
        ];

        yield 'fake regex' => [
            '/Foo/Bar/',
            false,
        ];

        yield 'minimal fake regex' => [
            '////',
            false,
        ];

        yield 'regular regex with flags' => [
            '/foo/iu',
            true,
        ];

        yield 'regular regex with invalid flags' => [
            '/foo/NOPE',
            false,
        ];
    }

    /**
     * @dataProvider regexProvider
     */
    public function test_it_can_validate_that_a_string_is_a_valid_regex_or_not(
        string $regex,
        ?string $expected
    ): void
    {
        $actual = $this->regexChecker->validateRegex($regex);

        self::assertSame($expected, $actual);
    }

    public static function regexProvider(): iterable
    {
        yield 'empty string' => [
            '',
            'Invalid regex: Internal error (code 1)',
        ];

        yield 'regular string' => [
            'foo',
            'Invalid regex: Internal error (code 1)',
        ];

        yield 'empty regex' => [
            '//',
            null,
        ];

        yield 'valid regex' => [
            '/foo/',
            null,
        ];

        yield 'invalid regex' => [
            '/foo/$',
            'Invalid regex: Internal error (code 1)',
        ];
    }
}
