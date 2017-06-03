<?php

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\PhpScoper\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;

final class IgnoreNamespaceScoperNodeVisitor extends NodeVisitorAbstract
{

    /**
     * @var array Class names to ignore when scoping
     */
    private $reserved;

    public function __construct(array $declaredClasses)
    {
        $this->reserved = $declaredClasses;
    }

    /**
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof UseUse && in_array((string) $node->name, $this->reserved)) {
            $node->setAttribute('phpscoper_ignore', true);
        }
        if ($node instanceof FullyQualified && in_array((string) $node, $this->reserved)) {
            $node->setAttribute('phpscoper_ignore', true);
        }
    }
}
