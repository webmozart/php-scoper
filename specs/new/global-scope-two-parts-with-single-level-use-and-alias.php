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
        'title' => 'New statement call of a namespaced class imported with an aliased use statement in the global scope',
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

    'New statement call of a namespaced class partially imported with an aliased use statement' => [
        'payload' => <<<'PHP'
<?php

namespace {
    class Foo {}
}

namespace Foo {
    class Bar {}
}

namespace {
    use Foo as A;
    
    new A\Bar();
}
----
<?php

namespace Humbug;

class Foo
{
}
namespace Humbug\Foo;

class Bar
{
}
namespace Humbug;

use Humbug\Foo as A;
new A\Bar();

PHP
    ],

    'New statement call of a namespaced class imported with an aliased use statement' => [
        'payload' => <<<'PHP'
<?php

namespace {
    class Foo {}
}

namespace Foo {
    class Bar {}
}

namespace {
    use Foo\Bar as A;
    
    new A();
}
----
<?php

namespace Humbug;

class Foo
{
}
namespace Humbug\Foo;

class Bar
{
}
namespace Humbug;

use Humbug\Foo\Bar as A;
new A();

PHP
    ],

    'FQ new statement call of a namespaced class with an aliased use statement' => [
        'payload' => <<<'PHP'
<?php

namespace {
    class Foo {}
}

namespace A {
    class Bar {}
}

namespace {
    use Foo as A;
    
    new \A\Bar();
}
----
<?php

namespace Humbug;

class Foo
{
}
namespace Humbug\A;

class Bar
{
}
namespace Humbug;

use Humbug\Foo as A;
new \Humbug\A\Bar();

PHP
    ],

    'FQ new statement call of a class with an aliased use statement' => [
        'payload' => <<<'PHP'
<?php

namespace {
    class A {}
}

namespace Foo {
    class Bar {}
}

namespace {
    use Foo\Bar as A;
    
    new \A();
}
----
<?php

namespace Humbug;

class A
{
}
namespace Humbug\Foo;

class Bar
{
}
namespace Humbug;

use Humbug\Foo\Bar as A;
new \Humbug\A();

PHP
    ],

    'New statement call of a whitelisted namespaced class partially imported with an aliased use statement' => [
        'whitelist' => ['Foo\Bar'],
        'registered-classes' => [
            ['Foo\Bar', 'Humbug\Foo\Bar'],
        ],
        'payload' => <<<'PHP'
<?php

namespace {
    class Foo {}
}

namespace Foo {
    class Bar {}
}

namespace {
    use Foo as A;
    
    new A\Bar();
}
----
<?php

namespace Humbug;

class Foo
{
}
namespace Humbug\Foo;

class Bar
{
}
\class_alias('Humbug\\Foo\\Bar', 'Foo\\Bar', \false);
namespace Humbug;

use Humbug\Foo as A;
new A\Bar();

PHP
    ],

    'New statement call of a whitelisted namespaced class imported with an aliased use statement' => [
        'whitelist' => ['Foo\Bar'],
        'registered-classes' => [
            ['Foo\Bar', 'Humbug\Foo\Bar'],
        ],
        'payload' => <<<'PHP'
<?php

namespace Foo {
    class Bar {}
}

namespace {
    use Foo\Bar as A;
    
    new A();
}
----
<?php

namespace Humbug\Foo;

class Bar
{
}
\class_alias('Humbug\\Foo\\Bar', 'Foo\\Bar', \false);
namespace Humbug;

use Humbug\Foo\Bar as A;
new A();

PHP
    ],
];
