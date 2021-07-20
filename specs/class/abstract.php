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

return [
    'meta' => [
        'title' => 'Abstract class declaration',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
        'exclude-namespaces' => [],
        'expose-global-constants' => true,
        'expose-global-classes' => false,
        'expose-global-functions' => true,
        'exclude-constants' => [],
        'exclude-classes' => [],
        'exclude-functions' => [],
        'registered-classes' => [],
        'registered-functions' => [],
    ],

    'Declaration in the global namespace' => <<<'PHP'
<?php

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}

PHP
    ,

    'Declaration in the global namespace with global classes whitelisted' => [
        'expose-global-classes' => true,
        'registered-classes' => [
            ['A', 'Humbug\A'],
        ],
        'payload' => <<<'PHP'
<?php

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}
\class_alias('Humbug\\A', 'A', \false);

PHP
        ,
    ],

    'Declaration in the global namespace with the global namespace which is namespaced whitelisted' => [
        'exclude-namespaces' => ['/^$/'],
        'payload' => <<<'PHP'
<?php

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace {
    abstract class A
    {
        public function a()
        {
        }
        public abstract function b();
    }
}

PHP
    ],

    'Declaration of a whitelisted class in the global namespace' => [
        'whitelist' => ['A'],
        'registered-classes' => [
            ['A', 'Humbug\A'],
        ],
        'payload' => <<<'PHP'
<?php

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}
\class_alias('Humbug\\A', 'A', \false);

PHP
    ],

    'Declaration of a whitelisted class in the global namespace which is whitelisted' => [
        'whitelist' => ['A'],
        'exclude-namespaces' => ['/^$/'],
        'registered-classes' => [
            ['A', 'Humbug\A'],
        ],
        'payload' => <<<'PHP'
<?php

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace {
    abstract class A
    {
        public function a()
        {
        }
        public abstract function b();
    }
}

PHP
    ],

    'Declaration in a namespace' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}

PHP
    ,

    'Declaration in a namespace with global classes whitelisted' => [
        'expose-global-classes' => true,
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}

PHP
        ,
    ],

    'Declaration in a whitelisted namespace' => [
        'exclude-namespaces' => ['/^Foo$/'],
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}

PHP
    ],

    'Declaration of a whitelisted class in a namespace' => [
        'whitelist' => ['Foo\A'],
        'registered-classes' => [
            ['Foo\A', 'Humbug\Foo\A'],
        ],
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}
\class_alias('Humbug\\Foo\\A', 'Foo\\A', \false);

PHP
    ],

    'Declaration of a namespaced class whitelisted with a pattern' => [
        'exclude-namespaces' => ['/^Foo\\\\A.*$/'],
        'registered-classes' => [],
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
}

abstract class AA {}

abstract class B {}

namespace Foo\A;

abstract class B {}

----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
}
abstract class AA
{
}
abstract class B
{
}
namespace Foo\A;

abstract class B
{
}

PHP
    ],

    'Declaration of a whitelisted class in a namespace with FQCN for the whitelist' => [
        'whitelist' => ['\Foo\A'],
        'registered-classes' => [
            ['Foo\A', 'Humbug\Foo\A'],
        ],
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}
\class_alias('Humbug\\Foo\\A', 'Foo\\A', \false);

PHP
    ],

    'Declaration of a class belonging to a whitelisted namespace' => [
        'exclude-namespaces' => ['/^.*$/'],
        'payload' => <<<'PHP'
<?php

namespace Foo;

abstract class A {
    public function a() {}
    abstract public function b();
}
----
<?php

namespace Foo;

abstract class A
{
    public function a()
    {
    }
    public abstract function b();
}

PHP
    ],

    'Multiple declarations in different namespaces with whitelisted classes' => [
        'whitelist' => ['Foo\WA', 'Bar\WB', 'WC'],
        'registered-classes' => [
            ['Foo\WA', 'Humbug\Foo\WA'],
            ['Bar\WB', 'Humbug\Bar\WB'],
            ['WC', 'Humbug\WC'],
        ],
        'payload' => <<<'PHP'
<?php

namespace Foo {

    abstract class A {
        public function a() {}
    }

    abstract class WA {
        public function a() {}
    }
}

namespace Bar {

    abstract class B {
        public function b() {}
    }

    abstract class WB {
        public function b() {}
    }
}

namespace {

    abstract class C {
        public function c() {}
    }

    abstract class WC {
        public function c() {}
    }
}
----
<?php

namespace Humbug\Foo;

abstract class A
{
    public function a()
    {
    }
}
abstract class WA
{
    public function a()
    {
    }
}
\class_alias('Humbug\\Foo\\WA', 'Foo\\WA', \false);
namespace Humbug\Bar;

abstract class B
{
    public function b()
    {
    }
}
abstract class WB
{
    public function b()
    {
    }
}
\class_alias('Humbug\\Bar\\WB', 'Bar\\WB', \false);
namespace Humbug;

abstract class C
{
    public function c()
    {
    }
}
abstract class WC
{
    public function c()
    {
    }
}
\class_alias('Humbug\\WC', 'WC', \false);

PHP
    ],
];
