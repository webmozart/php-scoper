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
        'title' => 'single-part new statements in a namespace with single-level use statements',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
    ],

    // See tests for the use statements as to why we don't touch the use statement.
    // Won't do anything here as this class is part of the global namespace.
    'single-part' => <<<'PHP'
<?php

namespace X;

use Foo;

new Foo();
----
<?php

namespace Humbug\X;

use Foo;

new \Foo();

PHP
    ,

    // See tests for the use statements as to why we don't touch the use statement.
    // Won't do anything here as this class is part of the global namespace.
    'FQ single-part' => <<<'PHP'
<?php

namespace X;

use Foo;

new \Foo();
----
<?php

namespace Humbug\X;

use Foo;

new \Foo();

PHP
    ,

    // See tests for the use statements as to why we don't touch the use statement.
    // See `scope.inc.php` for the built-in global whitelisted classes
    '(global) whitelisted single-part' => <<<'PHP'
<?php

namespace X;

use AppKernel;

new AppKernel();
----
<?php

namespace Humbug\X;

use Humbug\AppKernel;

new AppKernel();

PHP
    ,

    // See tests for the use statements as to why we don't touch the use statement.
    // See `scope.inc.php` for the built-in global whitelisted classes
    '(global) whitelisted FQ single-part' => <<<'PHP'
<?php

namespace X;

use AppKernel;

new \AppKernel();
----
<?php

namespace Humbug\X;

use Humbug\AppKernel;

new \Humbug\AppKernel();

PHP
    ,
];
