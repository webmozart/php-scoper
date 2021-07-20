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

namespace Humbug\PhpScoper;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Filesystem\Filesystem;

final class Container
{
    private Filesystem $filesystem;
    private ConfigurationFactory $configFactory;
    private Parser $parser;
    private ScoperFactory $scoperFactory;

    public function getFileSystem(): Filesystem
    {
        if (!isset($this->filesystem)) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    public function getConfigurationFactory(): ConfigurationFactory
    {
        if (!isset($this->configFactory)) {
            $this->configFactory = new ConfigurationFactory(
                $this->getFileSystem(),
                new ConfigurationWhitelistFactory(
                    new RegexChecker(),
                ),
            );
        }

        return $this->configFactory;
    }

    public function getScoperFactory(): ScoperFactory
    {
        if (!isset($this->scoperFactory)) {
            $this->scoperFactory = new ScoperFactory($this->getParser());
        }

        return $this->scoperFactory;
    }

    public function getParser(): Parser
    {
        if (!isset($this->parser)) {
            $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7, new Lexer());
        }

        return $this->parser;
    }
}
