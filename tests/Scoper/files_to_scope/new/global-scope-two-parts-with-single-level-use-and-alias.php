<?php

declare(strict_types=1);

// Fixture file used to compare what the expected result is for the scoping for a given input

return [
    'meta' => [
        'title' => 'two-parts new statements in the global scope with a single-level use statement and an alias',
        // Default values. If not specified will be the one used
        'prefix' => 'Humbug',
        'whitelist' => [],
    ],

    // See tests for the use statements as to why we don't touch the use statement.
    // The use statement cannot be prefixed, but as the class is in two-parts this
    // is enough to know we can prefix it.
    'two-parts' =>  <<<'PHP'
<?php

use Foo as X;

new X\Bar();
----
<?php

use Foo as X;

new \Humbug\Foo\Bar();

PHP
    ,

    // See tests for the use statements as to why we don't touch the use statement.
    // As there is nothing in PHP core with more than two-parts, we can safely prefix.
    'FQ two-parts' =>  <<<'PHP'
<?php

use Foo as X;

new \X\Bar();
----
<?php

use Foo as X;

new \Humbug\Foo\Bar();

PHP
    ,

    // See tests for the use statements as to why we don't touch the use statement.
    // If is whitelisted is made into a FQCN to avoid autoloading issues
    'whitelisted two-parts' =>  [
        'whitelist' => ['Foo\Bar'],
        'payload' => <<<'PHP'
<?php

use Foo as X;

new X\Bar();
----
<?php

use Foo as X;

new \Foo\Bar();

PHP
    ],

    // See tests for the use statements as to why we don't touch the use statement.
    'FQ whitelisted two-parts' =>  [
        'whitelist' => ['Foo\Bar'],
        'payload' => <<<'PHP'
<?php

use Foo as X;

new \X\Bar();
----
<?php

use Foo as X;

new \Foo\Bar();

PHP
    ],
];
