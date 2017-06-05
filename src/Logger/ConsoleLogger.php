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

namespace Humbug\PhpScoper\Logger;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @private
 * @final
 */
class ConsoleLogger
{
    private $application;
    private $io;

    public function __construct(Application $application, OutputInterface $output)
    {
        $this->io = $output;
        $this->application = $application;
    }

    /**
     * Output version details at start.
     */
    public function outputScopingStart()
    {
        $version = $this->application->getVersion();

        $this->io->writeLn(sprintf('PHP Scoper %s', $version));
    }

    /**
     * Output file count message if relevant.
     *
     * @param int $count
     */
    public function outputFileCount(int $count)
    {
        if (0 === $count) {
            $this->io->writeLn('No PHP files to scope located with given path(s).');
        }
    }

    /**
     * Output scoping success message.
     *
     * @param string $path
     */
    public function outputSuccess(string $path)
    {
        $this->io->writeLn(sprintf('Scoping %s. . . Success', $path));
    }

    /**
     * Output scoping failure message.
     *
     * @param string $path
     */
    public function outputFail(string $path)
    {
        $this->io->writeLn(sprintf('Scoping %s. . . Fail', $path));
    }

    public function outputScopingEnd()
    {
    }
}
