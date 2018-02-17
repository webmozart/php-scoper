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
        'title' => 'Global constant usage in the global scope',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
    ],

    [
        'spec' => <<<'SPEC'
Constant call in the global namespace:
- prefix the constant
- transforms the call into a FQ call
SPEC
        ,
        'payload' => <<<'PHP'
<?php

DUMMY_CONST;
----
<?php

namespace Humbug;

\Humbug\DUMMY_CONST;

PHP
    ],

    [
        'spec' => <<<'SPEC'
Internal constant call in the global namespace:
- do not prefix the constant
- transforms the call into a FQ call
SPEC
        ,
        'payload' => <<<'PHP'
<?php

DIRECTORY_SEPARATOR;
----
<?php

namespace Humbug;

\DIRECTORY_SEPARATOR;

PHP
    ],

    [
        'spec' => <<<'SPEC'
FQ constant call in the global namespace:
- prefix the constant
SPEC
    ,
        'payload' => <<<'PHP'
<?php

DUMMY_CONST;
----
<?php

namespace Humbug;

\Humbug\DUMMY_CONST;

PHP
    ],
];
