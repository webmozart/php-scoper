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
        'title' => 'two-parts new statements in a namespace scope',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
    ],

    // As there is nothing in PHP core with more than two-parts, we can safely prefix.
    'two-parts' => <<<'PHP'
<?php

namespace X;

new Foo\Bar();
----
<?php

namespace Humbug\X;

new Foo\Bar();

PHP
    ,

    // As there is nothing in PHP core with more than two-parts, we can safely prefix.
    'FQ two-parts' => <<<'PHP'
<?php

namespace X;

new \Foo\Bar();
----
<?php

namespace Humbug\X;

new \Humbug\Foo\Bar();

PHP
    ,

    // If is whitelisted is made into a FQCN to avoid autoloading issues
    'whitelisted two-parts' => [
        'whitelist' => ['X\Foo\Bar'],
        'payload' => <<<'PHP'
<?php

namespace X;

new Foo\Bar();
----
<?php

namespace Humbug\X;

new \X\Foo\Bar();

PHP
    ],

    'FQ whitelisted two-parts' => [
        'whitelist' => ['Foo\Bar'],
        'payload' => <<<'PHP'
<?php

namespace X;

new \Foo\Bar();
----
<?php

namespace Humbug\X;

new \Foo\Bar();

PHP
    ],
];
