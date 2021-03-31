<?php

declare(strict_types=1);

namespace Humbug\PhpScoper\Patcher;

use Humbug\PhpScoper\Extractor\IdentifierExtractor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Humbug\PhpScoper\Extractor\IdentifierExtractor
 */
class IdentifierExtractorTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__.'/../../fixtures/set033-identifier-extractor';

    public function test_can_extract_identifiers(): void
    {
        $identifiers = (new IdentifierExtractor())
                        ->addStub(self::FIXTURE_PATH . '/stubs.php')
                        ->extract();

        $this->assertIsArray($identifiers);
        $this->assertEquals(
            $identifiers,
            [
                'example_function',
                'ExampleTrait',
                'ExampleInterface',
                'ExampleAbstractClass',
                'ExampleFinalClass',
                'ExampleClass',
            ]
        );
    }

    public function test_returns_empty_array_when_using_empty_stubs_file(): void
    {
        $identifiers = (new IdentifierExtractor())
                        ->addStub(self::FIXTURE_PATH . '/empty-stubs.php')
                        ->extract();

        $this->assertEmpty($identifiers);
    }

    public function test_fails_with_invalid_stub_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new IdentifierExtractor())
            ->addStub(self::FIXTURE_PATH . '/invalid-stub-file.php')
            ->extract();
    }
}
