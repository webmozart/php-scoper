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

namespace Humbug\PhpScoper\Scoper\Symfony;

use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Whitelist;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use function Humbug\PhpScoper\create_fake_patcher;
use function is_a;

/**
 * @covers \Humbug\PhpScoper\Scoper\Symfony\XmlScoper
 */
class XmlScoperTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var Scoper
     */
    private $scoper;

    /**
     * @var ObjectProphecy<Scoper>
     */
    private ObjectProphecy $decoratedScoperProphecy;

    protected function setUp(): void
    {
        $this->decoratedScoperProphecy = $this->prophesize(Scoper::class);
        $decoratedScoper = $this->decoratedScoperProphecy->reveal();

        $this->scoper = new XmlScoper($decoratedScoper);
    }

    public function test_it_is_a_Scoper(): void
    {
        self::assertTrue(is_a(XmlScoper::class, Scoper::class, true));
    }

    /**
     * @dataProvider provideXmlFilesExtensions
     */
    public function test_it_can_scope_XML_files(string $file, bool $scoped): void
    {
        $prefix = 'Humbug';
        $patchers = [create_fake_patcher()];
        $whitelist = Whitelist::create(
            true,
            true,
            true,
            [],
            [],
            'Foo',
        );

        $contents = '';

        if (false === $scoped) {
            $this->decoratedScoperProphecy->scope(Argument::cetera())->willReturn($expected = 'scoped by decorated scoper');
            $scopedCount = 1;
        } else {
            $expected = $contents;
            $scopedCount = 0;
        }

        $actual = $this->scoper->scope($file, $contents, $prefix, $patchers, $whitelist);

        self::assertSame($expected, $actual);

        $this->decoratedScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes($scopedCount);
    }

    /**
     * @dataProvider provideXmlFiles
     */
    public function test_it_scopes_XML_files(string $contents, Whitelist $whitelist, string $expected, array $expectedClasses): void
    {
        $prefix = 'Humbug';
        $file = 'file.xml';
        $patchers = [create_fake_patcher()];

        $actual = $this->scoper->scope($file, $contents, $prefix, $patchers, $whitelist);

        self::assertSame($expected, $actual);

        self::assertSame($expectedClasses, $whitelist->getRecordedWhitelistedClasses());
        self::assertSame([], $whitelist->getRecordedWhitelistedFunctions());

        $this->decoratedScoperProphecy->scope(Argument::cetera())->shouldHaveBeenCalledTimes(0);
    }

    public static function provideXmlFilesExtensions(): iterable
    {
        yield ['file.xml', true];
        yield ['file.XML', true];
        yield ['file.xm', false];
        yield ['file.ml', false];
        yield ['file', false];
    }

    public static function provideXmlFiles(): iterable
    {
        yield 'empty' => [
            '',
            Whitelist::create(),
            '',
            [],
        ];

        yield [
            <<<'XML'
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <defaults public="false" />

        <service id="annotations.reader" class="Doctrine\Common\Annotations\AnnotationReader">
            <call method="addGlobalIgnoredName">
                <argument>required</argument>
                <!-- dummy arg to register class_exists as annotation loader only when required -->
                <argument type="service" id="annotations.dummy_registry" />
            </call>
        </service>

        <service id="annotations.dummy_registry" class="Doctrine\Common\Annotations\AnnotationRegistry">
            <call method="registerUniqueLoader">
                <argument>class_exists</argument>
            </call>
        </service>

        <service id="annotations.cached_reader" class="Doctrine\Common\Annotations\CachedReader">
            <argument type="service" id="annotations.reader" />
            <argument type="service">
                <service class="Doctrine\Common\Cache\ArrayCache" />
            </argument>
            <argument /><!-- Debug-Flag -->
        </service>

        <service id="annotations.filesystem_cache" class="Doctrine\Common\Cache\FilesystemCache">
            <argument /><!-- Cache-Directory -->
        </service>

        <service id="annotations.cache_warmer" class="Symfony\Bundle\FrameworkBundle\CacheWarmer\AnnotationsCacheWarmer">
            <argument type="service" id="annotations.reader" />
            <argument>%kernel.cache_dir%/annotations.php</argument>
            <argument type="service" id="cache.annotations" />
            <argument>#^Symfony\\(?:Component\\HttpKernel\\|Bundle\\FrameworkBundle\\Controller\\(?!AbstractController$|Controller$))#</argument>
            <argument>%kernel.debug%</argument>
        </service>

        <service id="annotations.cache" class="Symfony\Component\Cache\DoctrineProvider">
            <argument type="service">
                <service class="Symfony\Component\Cache\Adapter\PhpArrayAdapter">
                    <factory class="Symfony\Component\Cache\Adapter\PhpArrayAdapter" method="create" />
                    <argument>%kernel.cache_dir%/annotations.php</argument>
                    <argument type="service" id="cache.annotations" />
                </service>
            </argument>
        </service>

        <service id="annotation_reader" alias="annotations.reader" />
        <service id="Doctrine\Common\Annotations\Reader" alias="annotation_reader" />
    </services>
</container>

XML
            ,
            Whitelist::create(),
            <<<'XML'
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <defaults public="false" />

        <service id="annotations.reader" class="Humbug\Doctrine\Common\Annotations\AnnotationReader">
            <call method="addGlobalIgnoredName">
                <argument>required</argument>
                <!-- dummy arg to register class_exists as annotation loader only when required -->
                <argument type="service" id="annotations.dummy_registry" />
            </call>
        </service>

        <service id="annotations.dummy_registry" class="Humbug\Doctrine\Common\Annotations\AnnotationRegistry">
            <call method="registerUniqueLoader">
                <argument>class_exists</argument>
            </call>
        </service>

        <service id="annotations.cached_reader" class="Humbug\Doctrine\Common\Annotations\CachedReader">
            <argument type="service" id="annotations.reader" />
            <argument type="service">
                <service class="Humbug\Doctrine\Common\Cache\ArrayCache" />
            </argument>
            <argument /><!-- Debug-Flag -->
        </service>

        <service id="annotations.filesystem_cache" class="Humbug\Doctrine\Common\Cache\FilesystemCache">
            <argument /><!-- Cache-Directory -->
        </service>

        <service id="annotations.cache_warmer" class="Humbug\Symfony\Bundle\FrameworkBundle\CacheWarmer\AnnotationsCacheWarmer">
            <argument type="service" id="annotations.reader" />
            <argument>%kernel.cache_dir%/annotations.php</argument>
            <argument type="service" id="cache.annotations" />
            <argument>#^Symfony\\(?:Humbug\\Component\\HttpKernel\\|Humbug\\Bundle\\FrameworkBundle\\Controller\\(?!AbstractController$|Controller$))#</argument>
            <argument>%kernel.debug%</argument>
        </service>

        <service id="annotations.cache" class="Humbug\Symfony\Component\Cache\DoctrineProvider">
            <argument type="service">
                <service class="Humbug\Symfony\Component\Cache\Adapter\PhpArrayAdapter">
                    <factory class="Humbug\Symfony\Component\Cache\Adapter\PhpArrayAdapter" method="create" />
                    <argument>%kernel.cache_dir%/annotations.php</argument>
                    <argument type="service" id="cache.annotations" />
                </service>
            </argument>
        </service>

        <service id="annotation_reader" alias="annotations.reader" />
        <service id="Humbug\Doctrine\Common\Annotations\Reader" alias="annotation_reader" />
    </services>
</container>

XML
            ,
            [],
        ];

        yield 'PSR-4 service locator' => [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- Default configuration for services in *this* file -->
        <defaults autowire="true" autoconfigure="true" public="false" />

        <prototype namespace="App\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
        <prototype namespace="Acme\App\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
    </services>
</container>
XML
            ,
            Whitelist::create(),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- Default configuration for services in *this* file -->
        <defaults autowire="true" autoconfigure="true" public="false" />

        <prototype namespace="Humbug\App\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
        <prototype namespace="Humbug\Acme\App\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
    </services>
</container>
XML
            ,
            [],
        ];

        yield 'PSR-4 service locator with whitelist' => [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- Default configuration for services in *this* file -->
        <defaults autowire="true" autoconfigure="true" public="false" />

        <prototype namespace="Acme\Foo\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
        <prototype namespace="Acme\Bar\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
    </services>
</container>
XML
            ,
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo\*'
            ),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- Default configuration for services in *this* file -->
        <defaults autowire="true" autoconfigure="true" public="false" />

        <prototype namespace="Acme\Foo\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
        <prototype namespace="Humbug\Acme\Bar\" resource="../src/*" exclude="../src/{Entity,Migrations,Tests}" />
    </services>
</container>
XML
            ,
            [],
        ];

        yield 'service with alias' => [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="App\Mail\PhpMailer" public="false" />

        <service id="app.mailer" alias="App\Mail\PhpMailer" />
    </services>
</container>
XML
            ,
            Whitelist::create(),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Humbug\App\Mail\PhpMailer" public="false" />

        <service id="app.mailer" alias="Humbug\App\Mail\PhpMailer" />
    </services>
</container>
XML
            ,
            [],
        ];

        yield 'service with argument' => [
            <<<'XML'
<!-- app/config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="foo" class="App\Foo">
            <argument type="service">
                <service class="App\AnonymousBar" />
            </argument>
        </service>
    </services>
</container>
XML
            ,
            Whitelist::create(),
            <<<'XML'
<!-- app/config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="foo" class="Humbug\App\Foo">
            <argument type="service">
                <service class="Humbug\App\AnonymousBar" />
            </argument>
        </service>
    </services>
</container>
XML
            ,
            [],
        ];

        yield 'service with tag' => [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="App\Twig\AppExtension" public="false">
            <tag name="twig.extension" property="App\Twig\AppExtension" />
        </service>
    </services>
</container>
XML
            ,
            Whitelist::create(),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Humbug\App\Twig\AppExtension" public="false">
            <tag name="twig.extension" property="Humbug\App\Twig\AppExtension" />
        </service>
    </services>
</container>
XML
            ,
            [],
        ];

        yield [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Acme\Foo" />
        <service class="Acme\Bar" />
    </services>
</container>
XML
            ,
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\Foo',
            ),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Humbug\Acme\Foo" />
        <service class="Humbug\Acme\Bar" />
    </services>
</container>
XML
            ,
            [
                ['Acme\Foo', 'Humbug\Acme\Foo'],
            ],
        ];

        yield [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Foo">
            <argument id="Acme\Baz" />
        </service>
        <service class="Closure" />
    </services>
</container>
XML
            ,
            Whitelist::create(),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Foo">
            <argument id="Humbug\Acme\Baz" />
        </service>
        <service class="Closure" />
    </services>
</container>
XML
            ,
            [], // Whitelisting global classes in the service definitions is not supported at the moment. Provide a PR
                // if you are willing to add support for it.
        ];

        yield [
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Acme\Foo">
            <argument id="Acme\Bar" />
        </service>
        <service id="Emca\Foo">
            <argument id="Emca\Bar" />
        </service>
    </services>
</container>
XML
            ,
            Whitelist::create(
                true,
                true,
                true,
                [],
                [],
                'Acme\*',
            ),
            <<<'XML'
<!-- config/services.xml -->
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Acme\Foo">
            <argument id="Acme\Bar" />
        </service>
        <service id="Humbug\Emca\Foo">
            <argument id="Humbug\Emca\Bar" />
        </service>
    </services>
</container>
XML
            ,
            [],
        ];
    }
}
