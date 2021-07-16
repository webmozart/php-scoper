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
        'title' => 'Class static property call of a namespaced class in a namespace',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
        'whitelist-global-constants' => true,
        'expose-global-classes' => false,
        'expose-global-functions' => true,
        'excluded-constants' => [],
        'excluded-classes' => [],
        'excluded-functions' => [],
        'registered-classes' => [],
        'registered-functions' => [],
    ],

    'Constant call on a namespaced class' => <<<'PHP'
<?php

namespace X\PHPUnit {
    class Command {}
}

namespace X {
    PHPUnit\Command::$mainStaticProp;
}
----
<?php

namespace Humbug\X\PHPUnit;

class Command
{
}
namespace Humbug\X;

PHPUnit\Command::$mainStaticProp;

PHP
    ,

    'FQ constant call on a namespaced class' => <<<'PHP'
<?php

namespace PHPUnit {
    class Command {}
}

namespace X {
    \PHPUnit\Command::$mainStaticProp;
}
----
<?php

namespace Humbug\PHPUnit;

class Command
{
}
namespace Humbug\X;

\Humbug\PHPUnit\Command::$mainStaticProp;

PHP
    ,

    'Constant call on a whitelisted namespaced class' => [
        'whitelist' => ['X\PHPUnit\Command'],
        'registered-classes' => [
            ['X\PHPUnit\Command', 'Humbug\X\PHPUnit\Command'],
        ],
        'payload' => <<<'PHP'
<?php

namespace X\PHPUnit {
    class Command {}
}

namespace X {
    PHPUnit\Command::$mainStaticProp;
}
----
<?php

namespace Humbug\X\PHPUnit;

class Command
{
}
\class_alias('Humbug\\X\\PHPUnit\\Command', 'X\\PHPUnit\\Command', \false);
namespace Humbug\X;

PHPUnit\Command::$mainStaticProp;

PHP
    ],

    'FQ constant call on a whitelisted namespaced class' => [
        'whitelist' => ['PHPUnit\Command'],
        'registered-classes' => [
            ['PHPUnit\Command', 'Humbug\PHPUnit\Command'],
        ],
        'payload' => <<<'PHP'
<?php

namespace PHPUnit {
    class Command {}
}

namespace X {
    \PHPUnit\Command::$mainStaticProp;
}
----
<?php

namespace Humbug\PHPUnit;

class Command
{
}
\class_alias('Humbug\\PHPUnit\\Command', 'PHPUnit\\Command', \false);
namespace Humbug\X;

\Humbug\PHPUnit\Command::$mainStaticProp;

PHP
    ],
];
