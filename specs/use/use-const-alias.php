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
        'title' => 'Use statements for consts with aliases',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
    ],

    // As it is extremely rare to use a `use const` statement for a built-in const from the
    // global scope, we can relatively safely assume it is a user-land declare method which should
    // be prefixed.
    'const from the global scope' => <<<'PHP'
<?php

use const FOO as bar;

----
<?php

use Humbug\const FOO as bar;

PHP
    ,

    // As it is extremely rare to use a `use const` statement for a built-in const from the
    // global scope, we can relatively safely assume it is a user-land declare method which should
    // be prefixed.
    'absolute const from the global scope' => <<<'PHP'
<?php

use const \FOO as BAR;

----
<?php

use const Humbug\FOO as BAR;

PHP
    ,

    'already prefixed const form the global scope' => <<<'PHP'
<?php

use const Humbug\FOO as BAR;

----
<?php

use const Humbug\FOO as BAR;

PHP
    ,

    'already prefixed absolute const form the global scope' => <<<'PHP'
<?php

use const \Humbug\FOO as BAR;

----
<?php

use const Humbug\FOO as BAR;

PHP
    ,

    'namespaced const' => <<<'PHP'
<?php

use const Foo\BAR as BAZ;

----
<?php

use const Humbug\Foo\BAR as BAZ;

PHP
    ,

    'absolute namespaced const' => <<<'PHP'
<?php

use const \Foo\BAR as BAZ;

----
<?php

use const Humbug\Foo\BAR as BAZ;

PHP
    ,

    'already prefixed namespaced const' => <<<'PHP'
<?php

use const Humbug\Foo\BAR as BAZ;

----
<?php

use const Humbug\Foo\BAR as BAZ;

PHP
    ,

    'already prefixed absolute namespaced const' => <<<'PHP'
<?php

use const \Humbug\Foo\BAR as BAZ;

----
<?php

use const Humbug\Foo\BAR as BAZ;

PHP
    ,

    // Whitelist is for classes so this won't have any effect whatsoever
    'whitelisted namespaced const' => [
        'whitelist' => ['Foo\BAR'],
        'payload' => <<<'PHP'
<?php

use const Foo\BAR as BAZ;

----
<?php

use const Humbug\Foo\BAR as BAZ;

PHP
    ],
];
