<?php

declare(strict_types=1);

namespace Humbug\PhpScoper\Extractor;

use InvalidArgumentException;
use PhpParser\ParserFactory;
use function array_merge;
use function array_pop;
use function count;
use function file_exists;
use function file_get_contents;
use function get_class;
use function in_array;

class IdentifierExtractor
{
    public function __construct($statements = null)
    {
        $this->stubFiles = [];
        $this->extractStatements = $statements ?? [
            "PhpParser\Node\Stmt\Class_",
            "PhpParser\Node\Stmt\Interface_",
            "PhpParser\Node\Stmt\Trait_",
            "PhpParser\Node\Stmt\Function_"
        ];
    }

    /**
     * @param string $file
     * @return self
     */
    public function addStub($file): self
    {
        if (! file_exists($file)) {
            throw new InvalidArgumentException("File not found: " . $file);
        }

        $this->stubFiles[] = $file;
        return $this;
    }

    /**
     * @return array
     */
    public function extract(): array
    {
        $identifiers = [];
        foreach ($this->stubFiles as $file) {
            $content = file_get_contents($file);
            $ast = $this->generateAst($content);
            $identifiers = array_merge($identifiers, $this->extractIdentifiersFromAst($ast));
        }

        return $identifiers;
    }

    /**
     * @param string $code
     * @return array<Node\Stmt[]>|null
     */
    protected function generateAst($code): array
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        return $parser->parse($code);
    }

    /**
     * @param array<Node\Stmt[]> $ast
     * @return array<Node\Stmt[]>|null
     */
    protected function extractIdentifiersFromAst($ast): array
    {
        $globals = [];
        $items = $ast;

        while (count($items) > 0) {
            $item = array_pop($items);

            if (isset($item->stmts)) {
                $items = array_merge($items, $item->stmts);
            }

            if (in_array(get_class($item), $this->extractStatements)) {
                $globals[] = $item->name->name;
            }
        }

        return $globals;
    }
}
