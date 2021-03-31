<?php

declare(strict_types=1);

namespace Humbug\PhpScoper\Patcher;

use Generator;
use Humbug\PhpScoper\Extractor\IdentifierExtractor;
use Humbug\PhpScoper\Patcher\RemovePrefixFromIdentifiersPatcher;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function preg_replace;

/**
 * @covers \Humbug\PhpScoper\Patcher\RemovePrefixFromIdentifiersPatcher
 */
class RemovePrefixFromIdentifiersPatcherTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__.'/../../fixtures/set034-remove-prefix-patcher';

    /**
     * @dataProvider provideFiles
     */
    public function test_patch_wordpress_files(string $filePath, string $contents, string $expected): void
    {
        $identifiers = (new IdentifierExtractor())
                            ->addStub(self::FIXTURE_PATH . '/stubs.php')
                            ->extract();

        $actual = (new RemovePrefixFromIdentifiersPatcher($identifiers))->__invoke($filePath, 'Humbug', $contents);

        $this->assertSame($expected, $actual);
    }

    public function provideFiles(): Generator
    {
        $files = [
            'original/wordpress-missing-identifiers.php' => 'patched/wordpress-missing-identifiers.php',
            'original/wordpress-complete-identifiers.php' => 'patched/wordpress-complete-identifiers.php'
        ];

        foreach ($files as $originalFile => $patchedFile) {
            $originalContent = file_get_contents(self::FIXTURE_PATH.'/'.$originalFile);
            $patchedContent = file_get_contents(self::FIXTURE_PATH.'/'.$patchedFile);

            $originalContent = preg_replace('/\s*/', '', $originalContent);
            $patchedContent = preg_replace('/\s*/', '', $patchedContent);

            yield [$originalFile, $originalContent, $patchedContent];
        }
    }
}
