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

/**
 * @covers \Humbug\PhpScoper\Scoper\Composer\InstalledPackagesScoper
 * @covers \Humbug\PhpScoper\Scoper\Composer\AutoloadPrefixer
 */
class InstalledPackagesScoperTest extends TestCase
{
    public function test_it_is_a_Scoper()
    {
        $this->assertTrue(is_a(InstalledPackagesScoper::class, Scoper::class, true));
    }

    public function test_delegates_scoping_to_the_decorated_scoper_if_is_not_a_installed_file()
    {
        $filePath = 'file.php';
        $fileContents = '';
        $prefix = 'Humbug';
        $patchers = [create_fake_patcher()];
        $whitelist = ['Foo'];

        /** @var Scoper|ObjectProphecy $decoratedScoperProphecy */
        $decoratedScoperProphecy = $this->prophesize(Scoper::class);
        $decoratedScoperProphecy
            ->scope($filePath, $fileContents, $prefix, $patchers, $whitelist)
            ->willReturn(
                $expected = 'Scoped content'
            )
        ;
        /** @var Scoper $decoratedScoper */
        $decoratedScoper = $decoratedScoperProphecy->reveal();

        $scoper = new InstalledPackagesScoper($decoratedScoper);

        $actual = $scoper->scope($filePath, $fileContents, $prefix, $patchers, $whitelist);

        $this->assertSame($expected, $actual);

        $decoratedScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    /**
     * @dataProvider provideInstalledPackagesFiles
     */
    public function test_it_prefixes_the_composer_autoloaders(string $fileContents, string $expected)
    {
        $filePath = 'composer/installed.json';

        $scoper = new InstalledPackagesScoper(new FakeScoper());

        $prefix = 'Foo';
        $patchers = [create_fake_patcher()];
        $whitelist = ['Foo'];

        $actual = $scoper->scope($filePath, $fileContents, $prefix, $patchers, $whitelist);

        $this->assertSame($expected, $actual);
    }

    public function provideInstalledPackagesFiles()
    {
        yield [
            <<<'JSON'
[
    {
        "name": "beberlei/assert",
        "version": "v2.7.6",
        "version_normalized": "2.7.6.0",
        "source": {
            "type": "git",
            "url": "https://github.com/beberlei/assert.git",
            "reference": "8726e183ebbb0169cb6cb4832e22ebd355524563"
        },
        "dist": {
            "type": "zip",
            "url": "https://api.github.com/repos/beberlei/assert/zipball/8726e183ebbb0169cb6cb4832e22ebd355524563",
            "reference": "8726e183ebbb0169cb6cb4832e22ebd355524563",
            "shasum": ""
        },
        "require": {
            "ext-mbstring": "*",
            "php": ">=5.3"
        },
        "require-dev": {
            "friendsofphp/php-cs-fixer": "^2.1.1",
            "phpunit/phpunit": "^4|^5"
        },
        "time": "2017-05-04T02:00:24+00:00",
        "type": "library",
        "installation-source": "dist",
        "autoload": {
            "psr-4": {
                "Assert\\": "lib/Assert"
            },
            "files": [
                "lib/Assert/functions.php"
            ]
        },
        "notification-url": "https://packagist.org/downloads/",
        "license": [
            "BSD-2-Clause"
        ],
        "authors": [
            {
                "name": "Benjamin Eberlei",
                "email": "kontakt@beberlei.de",
                "role": "Lead Developer"
            },
            {
                "name": "Richard Quadling",
                "email": "rquadling@gmail.com",
                "role": "Collaborator"
            }
        ],
        "description": "Thin assertion library for input validation in business models.",
        "keywords": [
            "assert",
            "assertion",
            "validation"
        ]
    }
]

JSON
            ,
            <<<'JSON'
[
    {
        "name": "beberlei\/assert",
        "version": "v2.7.6",
        "version_normalized": "2.7.6.0",
        "source": {
            "type": "git",
            "url": "https:\/\/github.com\/beberlei\/assert.git",
            "reference": "8726e183ebbb0169cb6cb4832e22ebd355524563"
        },
        "dist": {
            "type": "zip",
            "url": "https:\/\/api.github.com\/repos\/beberlei\/assert\/zipball\/8726e183ebbb0169cb6cb4832e22ebd355524563",
            "reference": "8726e183ebbb0169cb6cb4832e22ebd355524563",
            "shasum": ""
        },
        "require": {
            "ext-mbstring": "*",
            "php": ">=5.3"
        },
        "require-dev": {
            "friendsofphp\/php-cs-fixer": "^2.1.1",
            "phpunit\/phpunit": "^4|^5"
        },
        "time": "2017-05-04T02:00:24+00:00",
        "type": "library",
        "installation-source": "dist",
        "autoload": {
            "psr-4": {
                "Foo\\Assert\\": "lib\/Assert"
            },
            "files": [
                "lib\/Assert\/functions.php"
            ]
        },
        "notification-url": "https:\/\/packagist.org\/downloads\/",
        "license": [
            "BSD-2-Clause"
        ],
        "authors": [
            {
                "name": "Benjamin Eberlei",
                "email": "kontakt@beberlei.de",
                "role": "Lead Developer"
            },
            {
                "name": "Richard Quadling",
                "email": "rquadling@gmail.com",
                "role": "Collaborator"
            }
        ],
        "description": "Thin assertion library for input validation in business models.",
        "keywords": [
            "assert",
            "assertion",
            "validation"
        ]
    }
]
JSON
        ];
    }
}
