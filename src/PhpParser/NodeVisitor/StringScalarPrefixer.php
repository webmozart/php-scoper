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

namespace Humbug\PhpScoper\PhpParser\NodeVisitor;

use function array_shift;
use function array_values;
use Humbug\PhpScoper\Reflector;
use Humbug\PhpScoper\Whitelist;
use function implode;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\NodeVisitorAbstract;
use function array_key_exists;
use function count;
use function Humbug\PhpScoper\is_stringable;
use function in_array;
use function is_string;
use function preg_match;
use function strlen;
use function strpos;
use function substr;

/**
 * Prefixes the string scalar values when appropriate.
 *
 * ```
 * $x = 'Foo\Bar';
 * ```
 *
 * =>
 *
 * ```
 * $x = 'Humbug\Foo\Bar';
 * ```
 *
 * @private
 */
final class StringScalarPrefixer extends NodeVisitorAbstract
{
    private const SPECIAL_FUNCTION_NAMES = [
        'is_a',
        'is_subclass_of',
        'interface_exists',
        'class_exists',
        'trait_exists',
        'function_exists',
        'class_alias',
        'define',
    ];

    private $prefix;
    private $whitelist;
    private $reflector;

    public function __construct(string $prefix, Whitelist $whitelist, Reflector $reflector)
    {
        $this->prefix = $prefix;
        $this->whitelist = $whitelist;
        $this->reflector = $reflector;
    }

    /**
     * @inheritdoc
     */
    public function enterNode(Node $node): Node
    {
        $isSpecialFunction = false;

        return $node instanceof String_
            ? $this->prefixStringScalar($node, $isSpecialFunction)
            : $node
        ;
    }

    private function prefixStringScalar(String_ $string): String_
    {
        if (false === (ParentNodeAppender::hasParent($string) && is_string($string->value))
            || 1 !== preg_match('/^((\\\\)?[\p{L}_]+)$|((\\\\)?(?:[\p{L}_]+\\\\+)+[\p{L}_]+)$/u', $string->value)
        ) {
            return $string;
        }

        if ($this->whitelist->belongsToWhitelistedNamespace($string->value)) {
            return $string;
        }

        // From this point either the symbol belongs to the global namespace or the symbol belongs to the symbol
        // namespace is whitelisted

        $parentNode = ParentNodeAppender::getParent($string);

        // The string scalar either has a class form or a simple string which can either be a symbol from the global
        // namespace or a completely unrelated string.

        if ($parentNode instanceof Arg) {
            return $this->prefixStringArg($string, $parentNode);
        }

        if ($parentNode instanceof ArrayItem) {
            return $this->prefixArrayItemString($string, $parentNode);
        }

        if (false === (
                $parentNode instanceof Assign
                || $parentNode instanceof Param
                || $parentNode instanceof Const_
                || $parentNode instanceof PropertyProperty
            )
        ) {
            return $string;
        }

        if ($this->belongsToTheGlobalNamespace($string)) {
            return $string;
        }

        return $this->reflector->isClassInternal($string->value) || $this->belongsToTheGlobalNamespace($string)
            ? $string
            : $this->createPrefixedString($string)
        ;
    }

    private function prefixStringArg(String_ $string, Arg $parentNode): String_
    {
        if (null === $functionNode = ParentNodeAppender::findParent($parentNode)) {
            return $this->reflector->isClassInternal($string->value) || $this->belongsToTheGlobalNamespace($string)
                ? $string
                : $this->createPrefixedString($string)
            ;
        }

        if (false === ($functionNode instanceof FuncCall)) {
            return $this->reflector->isClassInternal($string->value) || $this->belongsToTheGlobalNamespace($string)
                ? $string
                : $this->createPrefixedString($string)
            ;
        }
        /** @var FuncCall $functionNode */

        // In the case of a function call, we allow to prefix strings which could be classes belonging to the global
        // namespace in some cases
        $functionName = is_stringable($functionNode->name) ? (string) $functionNode->name : null;

        if (null === $functionName
            || false === in_array($functionName, self::SPECIAL_FUNCTION_NAMES, true)
        ) {
            return $this->reflector->isClassInternal($string->value) || $this->belongsToTheGlobalNamespace($string)
                ? $string
                : $this->createPrefixedString($string)
            ;

            return $string;
        }

        if ('function_exists' === $functionName) {
            return $this->reflector->isFunctionInternal($string->value) // TODO: belongs to a whitelisted namespace?
                ? $string
                : $this->createPrefixedString($string)
            ;
        }

        $isConstantNode = $this->isConstantNode($string);

        if (false === $isConstantNode) {
            if ('define' ===$functionName
                && $parentNode !== $functionNode->args[0]
                && $this->belongsToTheGlobalNamespace($string)
            ) {
                return $string;
            }

            return $this->reflector->isClassInternal($string->value)
                ? $string
                : $this->createPrefixedString($string)
            ;
        }

        if ($this->whitelist->isSymbolWhitelisted($string->value, true)
            || $this->whitelist->isGlobalWhitelistedConstant($string->value)
        ) {
            return $string;
        }

        return $this->createPrefixedString($string);
    }

    private function prefixArrayItemString(String_ $string, ArrayItem $parentNode): String_
    {
        // ArrayItem can lead to two results: either the string is used for `spl_autoload_register()`, e.g.
        // `spl_autoload_register(['Swift', 'autoload'])` in which case the string `'Swift'` is guaranteed to be class
        // name, or something else in which case a string like `'Swift'` can be anything and cannot be prefixed.

        $arrayItemNode = $parentNode;

        if (false === ParentNodeAppender::hasParent($parentNode)) {
            return $string;
        }

        $parentNode = ParentNodeAppender::getParent($parentNode);

        if (false === ($parentNode instanceof Array_) || false === ParentNodeAppender::hasParent($parentNode)) {
            return $string;
        }

        /** @var Array_ $arrayNode */
        $arrayNode = $parentNode;
        $parentNode = ParentNodeAppender::getParent($parentNode);

        if (false === ($parentNode instanceof Arg)
            || null === $functionNode = ParentNodeAppender::findParent($parentNode)
        ) {
            return $this->reflector->isClassInternal($string->value)
                ? $string
                : $this->createPrefixedString($string)
            ;

            return $string;
        }

        $functionNode = ParentNodeAppender::getParent($parentNode);

        if (false === ($functionNode instanceof FuncCall)) {
            return $string;
        }

        /** @var FuncCall $functionNode */
        if (false === is_stringable($functionNode->name)) {
            return $string;
        }

        $functionName = (string) $functionNode->name;

        if ('spl_autoload_register' === $functionName
            && array_key_exists(0, $arrayNode->items)
            && $arrayItemNode === $arrayNode->items[0]
            && false === $this->reflector->isClassInternal($string->value)
        ) {
            return $this->createPrefixedString($string);
        }

        return $string;
    }

    private function isConstantNode(String_ $node): bool
    {
        $parent = ParentNodeAppender::getParent($node);

        if (false === ($parent instanceof Arg)) {
            return false;
        }

        /** @var Arg $parent */
        $argParent = ParentNodeAppender::getParent($parent);

        if (false === ($argParent instanceof FuncCall)) {
            return false;
        }

        /* @var FuncCall $argParent */
        if ('define' !== (string) $argParent->name) {
            return false;
        }

        return $parent === $argParent->args[0];
    }

    private function createPrefixedString(String_ $previous): String_
    {
        $previousValueParts = array_values(
            array_filter(
                explode('\\', $previous->value)
            )
        );

        if ($this->prefix === $previousValueParts[0]) {
            array_shift($previousValueParts);
        }

        $previousValue = implode('\\', $previousValueParts);

        $string = new String_(
            (string) FullyQualified::concat($this->prefix, $previousValue),
            $previous->getAttributes()
        );

        $string->setAttribute(ParentNodeAppender::PARENT_ATTRIBUTE, $string);

        return $string;
    }

    private function belongsToTheGlobalNamespace(String_ $string): bool
    {
        return strlen($string->value) < 1 || 0 === (int) strpos($string->value, '\\', 1);
    }
}
