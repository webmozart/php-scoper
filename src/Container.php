<?php

declare(strict_types=1);

namespace Humbug\PhpScoper;


use Humbug\PhpScoper\PhpParser\TraverserFactory;
use Humbug\PhpScoper\Scoper\Composer\InstalledPackagesScoper;
use Humbug\PhpScoper\Scoper\Composer\JsonFileScoper;
use Humbug\PhpScoper\Scoper\NullScoper;
use Humbug\PhpScoper\Scoper\PatchScoper;
use Humbug\PhpScoper\Scoper\PhpScoper;
use Humbug\PhpScoper\Scoper\SymfonyScoper;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Ast\Locator;
use Roave\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;

final class Container
{
    private $parser;
    private $reflector;
    private $scoper;

    public function getScoper(): Scoper
    {
        if (null === $this->scoper) {
            $this->scoper = new PatchScoper(
                new PhpScoper(
                    $this->getParser(),
                    new JsonFileScoper(
                        new InstalledPackagesScoper(
                            new SymfonyScoper(
                                new NullScoper()
                            )
                        )
                    ),
                    new TraverserFactory($this->getReflector())
                )
            );
        }

        return $this->scoper;
    }

    public function getParser(): Parser
    {
        if (null === $this->parser) {
            $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        }

        return $this->parser;
    }

    public function getReflector(): Reflector
    {
        if (null === $this->reflector) {
            $phpParser = $this->getParser();
            $astLocator = new Locator($phpParser);

            $sourceLocator = new MemoizingSourceLocator(
                new PhpInternalSourceLocator($astLocator)
            );
            $classReflector = new ClassReflector($sourceLocator);

            $this->reflector = new Reflector($classReflector);
        }

        return $this->reflector;
    }
}