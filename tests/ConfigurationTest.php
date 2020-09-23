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

use Humbug\PhpScoper\Patcher\SymfonyPatcher;
use InvalidArgumentException;
use function KevinGH\Box\FileSystem\dump_file;

/**
 * @covers \Humbug\PhpScoper\Configuration
 */
class ConfigurationTest extends FileSystemTestCase
{
    public function test_it_can_be_created_without_a_file(): void
    {
        $configuration = Configuration::load();

        $this->assertSame([], $configuration->getWhitelistedFiles());
        $this->assertEquals(
            Whitelist::create(true, true, true),
            $configuration->getWhitelist()
        );
        $this->assertNull($configuration->getPath());
        $this->assertNull($configuration->getPrefix());
        $this->assertSame([], $configuration->getFilesWithContents());
        $this->assertEquals([new SymfonyPatcher()], $configuration->getPatchers());
        $this->assertNull($configuration->getOutputDir());
    }

    public function test_it_cannot_create_a_configuration_with_an_invalid_key(): void
    {
        dump_file(
            'scoper.inc.php',
            <<<'PHP'
<?php

return [
    'unknown key' => 'val',
];
PHP
        );

        try {
            Configuration::load($this->tmp.'/scoper.inc.php');

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Invalid configuration key value "unknown key" found.',
                $exception->getMessage()
            );
        }
    }

    public function test_it_can_create_a_complete_configuration(): void
    {
        dump_file(
            'scoper.inc.php',
            <<<'PHP'
<?php

return [
    'prefix' => 'MyPrefix',
    'files-whitelist' => ['file1', 'file2'],
    'whitelist-global-constants' => false,
    'whitelist-global-classes' => false,
    'whitelist-global-functions' => false,
    'whitelist' => ['Foo', 'Bar\*'],
    'output-dir' => 'foo/bar'
];
PHP
        );
        touch('file1');

        $configuration = Configuration::load($this->tmp.DIRECTORY_SEPARATOR.'scoper.inc.php');

        $this->assertSame([$this->tmp.DIRECTORY_SEPARATOR.'file1'], $configuration->getWhitelistedFiles());
        $this->assertEquals(
            Whitelist::create(false, false, false, 'Foo', 'Bar\*'),
            $configuration->getWhitelist()
        );
        $this->assertSame($this->tmp.DIRECTORY_SEPARATOR.'scoper.inc.php', $configuration->getPath());
        $this->assertSame('MyPrefix', $configuration->getPrefix());
        $this->assertSame([], $configuration->getFilesWithContents());
        $this->assertEquals([new SymfonyPatcher()], $configuration->getPatchers());
        $this->assertEquals('foo/bar', $configuration->getOutputDir());
    }
}
