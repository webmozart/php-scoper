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

namespace Humbug\PhpScoper\NodeVisitor;

use Humbug\PhpScoper\NodeVisitor\Resolver\FullyQualifiedNameResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeVisitorAbstract;
use Roave\BetterReflection\Reflector\ClassReflector;

/**
 * ```
 * new Foo\Bar();
 * ```.
 *
 * =>
 *
 * ```
 * new \Humbug\Foo\Bar();
 * ```
 *
 * @private
 */
final class NameStmtPrefixer extends NodeVisitorAbstract
{
    private const PHP_FUNCTION_KEYWORDS = [
        'self',
        'static',
        'parent',
    ];

    private $prefix;
    private $whitelist;
    private $nameResolver;
    private $classReflector;

    /**
     * @param string                     $prefix
     * @param string[]                   $whitelist
     * @param FullyQualifiedNameResolver $nameResolver
     * @param ClassReflector             $classReflector
     */
    public function __construct(
        string $prefix,
        array $whitelist,
        FullyQualifiedNameResolver $nameResolver,
        ClassReflector $classReflector
    ) {
        $this->prefix = $prefix;
        //$this->whitelist = $whitelist;
        $this->nameResolver = $nameResolver;
        $this->classReflector = $classReflector;
    }

    /**
     * @inheritdoc
     */
    public function enterNode(Node $node): Node
    {
        return ($node instanceof Name && AppendParentNode::hasParent($node))
            ? $this->prefixName($node)
            : $node
        ;
    }

    private function prefixName(Name $name): Node
    {
        $parentNode = AppendParentNode::getParent($name);

        if (false === (
                $parentNode instanceof ConstFetch
                || $parentNode instanceof ClassConstFetch
                || $parentNode instanceof Param
                || $parentNode instanceof FuncCall
                || $parentNode instanceof StaticCall
                || $parentNode instanceof New_
                || $parentNode instanceof Class_
                || $parentNode instanceof Interface_
            )
        ) {
            return $name;
        }

        if (
            (
                $parentNode instanceof FuncCall
                || $parentNode instanceof StaticCall
                || $parentNode instanceof ClassConstFetch
                || $parentNode instanceof New_
                || $parentNode instanceof Param
            )
            && in_array((string) $name, self::PHP_FUNCTION_KEYWORDS)
        ) {
            return $name;
        }

        $resolvedValue = $this->nameResolver->resolveName($name);

        $resolvedName = $resolvedValue->getName();

        // Skip if is already prefixed
        if ($this->prefix === $resolvedName->getFirst()) {
            return $resolvedName;
        }

        // Check if the class can be prefixed
        if (false === ($parentNode instanceof ConstFetch || $parentNode instanceof FuncCall)) {
            if ($this->classReflector->reflect($resolvedName->toString())->isInternal()) {
                return $resolvedName;
            }
        }

        if ($parentNode instanceof ConstFetch
            && 1 === count($resolvedName->parts)
            && null === $resolvedValue->getUse()
        ) {
            return $resolvedName;
        }

        if ($parentNode instanceof FuncCall
            && 1 === count($resolvedName->parts)
            && null === $resolvedValue->getUse()
        ) {
            return $resolvedName;
        }

        return FullyQualified::concat($this->prefix, $resolvedName->toString(), $resolvedName->getAttributes());
    }
}
