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

namespace Humbug\PhpScoper\Scoper\Composer;

use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Scoper\FakeScoper;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use function Humbug\PhpScoper\create_fake_patcher;
use function Humbug\PhpScoper\create_fake_whitelister;
use function Humbug\PhpScoper\escape_path;
use function Humbug\PhpScoper\make_tmp_dir;
use function Humbug\PhpScoper\remove_dir;

/**
 * @covers \Humbug\PhpScoper\Scoper\Composer\JsonFileScoper
 * @covers \Humbug\PhpScoper\Scoper\Composer\AutoloadPrefixer
 */
class JsonFileScoperTest extends TestCase
{
    /**
     * @var string
     */
    private $cwd;

    /**
     * @var string
     */
    private $tmp;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        if (null == $this->tmp) {
            $this->cwd = getcwd();
            $this->tmp = make_tmp_dir('scoper', __CLASS__);
        }

        chdir($this->tmp);
    }

    /**
     * @inheritdoc
     */
    public function tearDown()
    {
        chdir($this->cwd);

        remove_dir($this->tmp);
    }

    public function test_it_is_a_Scoper()
    {
        $this->assertTrue(is_a(JsonFileScoper::class, Scoper::class, true));
    }

    public function test_delegates_scoping_to_the_decorated_scoper_if_is_not_a_composer_file()
    {
        $filePath = escape_path($this->tmp.'/file.php');
        $prefix = 'Humbug';
        $patchers = [create_fake_patcher()];
        $whitelist = ['Foo'];
        $whitelister = create_fake_whitelister();

        touch($filePath);
        file_put_contents($filePath, '');

        /** @var Scoper|ObjectProphecy $decoratedScoperProphecy */
        $decoratedScoperProphecy = $this->prophesize(Scoper::class);
        $decoratedScoperProphecy
            ->scope($filePath, $prefix, $patchers, $whitelist, $whitelister)
            ->willReturn(
                $expected = 'Scoped content'
            )
        ;
        /** @var Scoper $decoratedScoper */
        $decoratedScoper = $decoratedScoperProphecy->reveal();

        $scoper = new JsonFileScoper($decoratedScoper);

        $actual = $scoper->scope($filePath, $prefix, $patchers, $whitelist, $whitelister);

        $this->assertSame($expected, $actual);

        $decoratedScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    /**
     * @dataProvider provideComposerFiles
     */
    public function test_it_prefixes_the_composer_autoloaders(string $fileContent, string $expected)
    {
        touch($filePath = escape_path($this->tmp.'/composer.json'));
        file_put_contents($filePath, $fileContent);

        $scoper = new JsonFileScoper(new FakeScoper());

        $prefix = 'Foo';
        $patchers = [create_fake_patcher()];
        $whitelist = ['Foo'];
        $whitelister = create_fake_whitelister();

        $actual = $scoper->scope($filePath, $prefix, $patchers, $whitelist, $whitelister);

        $this->assertSame($expected, $actual);
    }

    public function provideComposerFiles()
    {
        yield [
            <<<'JSON'
{
    "bin": ["bin/php-scoper"],
    "autoload": {
        "psr-4": {
            "Humbug\\PhpScoper\\": "src/"
        },
        "files": [
            "src/functions.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Humbug\\PhpScoper\\": "tests/"
        },
        "files": [
            "tests/functions.php"
        ]
    }
}

JSON
            ,
            <<<'JSON'
{
    "bin": [
        "bin\/php-scoper"
    ],
    "autoload": {
        "psr-4": {
            "Foo\\Humbug\\PhpScoper\\": "src\/"
        },
        "files": [
            "src\/functions.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Foo\\Humbug\\PhpScoper\\": "tests\/"
        },
        "files": [
            "tests\/functions.php"
        ]
    }
}
JSON
        ];
    }

    /**
     * @dataProvider providePSRZeroComposerFiles
     */
    public function test_it_prefixes_psr0_autoloaders(string $fileContent, string $expected)
    {
        touch($filePath = escape_path($this->tmp.'/composer.json'));
        file_put_contents($filePath, $fileContent);

        $scoper = new JsonFileScoper(new FakeScoper());

        $prefix = 'Foo';
        $patchers = [create_fake_patcher()];
        $whitelist = ['Foo'];
        $whitelister = create_fake_whitelister();

        $actual = $scoper->scope($filePath, $prefix, $patchers, $whitelist, $whitelister);

        $this->assertSame($expected, $actual);
    }

    public function providePSRZeroComposerFiles()
    {
        yield [
            <<<'JSON'
{
    "bin": ["bin/php-scoper"],
    "autoload": {
        "psr-0": {
            "Humbug\\PhpScoper\\": "src/"
        },
        "psr-4": {
            "BarFoo\\": [
                "lib/",
                "dev/"
            ]
        },
        "files": [
            "src/functions.php"
        ]
    },
    "autoload-dev": {
        "psr-0": {
            "Humbug\\PhpScoper\\": "tests/"
        },
        "psr-4": {
            "Bar\\": "folder\/" 
        },
        "files": [
            "tests/functions.php"
        ]
    }
}

JSON
            ,
            <<<'JSON'
{
    "bin": [
        "bin\/php-scoper"
    ],
    "autoload": {
        "psr-4": {
            "Foo\\BarFoo\\": [
                "lib\/",
                "dev\/"
            ],
            "Foo\\Humbug\\PhpScoper\\": "src\/Humbug\/PhpScoper\/\/"
        },
        "files": [
            "src\/functions.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Foo\\Bar\\": "folder\/",
            "Foo\\Humbug\\PhpScoper\\": "tests\/Humbug\/PhpScoper\/\/"
        },
        "files": [
            "tests\/functions.php"
        ]
    }
}
JSON
        ];
        yield 'psr zero and four with the same namespace get merged' => [
            <<<'JSON'
{
    "autoload": {
        "psr-0": {
            "Bar\\": "src/"
        },
        "psr-4": {
            "Bar\\": "lib/"
        }
     }
}
JSON
            ,
            <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "Foo\\Bar\\": [
                "lib\/",
                "src\/Bar\/\/"
            ]
        }
    }
}
JSON
        ];
        yield 'psr zero and four get merged if either of them have multiple entries' => [
            <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "Bar\\": [
                "lib/",
                "src/"
            ]
        },
        "psr-0": {
            "Bar\\": "test"
        }
    },
    "autoload-dev": {
        "psr-0": {
            "Baz\\": [
                "folder/",
                "check/"
            ]
        },
        "psr-4": {
            "Baz\\": "loader/"
        }
    }
}
JSON
            ,
            <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "Foo\\Bar\\": [
                "lib\/",
                "src\/",
                "test\/Bar\/\/"
            ]
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Foo\\Baz\\": [
                "folder\/Baz\/\/",
                "check\/Baz\/\/",
                "loader\/"
            ]
        }
    }
}
JSON
        ];
        yield 'psr zero gets converted to psr4' => [
            <<<'JSON'
{
    "autoload": {
        "psr-0": {
            "Bar\\": "src/"
        }
    }
}
JSON
            ,
            <<<'JSON'
{
    "autoload": {
        "psr-4": {
            "Foo\\Bar\\": "src\/Bar\/\/"
        }
    }
}
JSON
        ];
    }
}
