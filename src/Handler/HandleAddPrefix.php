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

namespace Humbug\PhpScoper\Handler;

use function Humbug\PhpScoper\get_common_path;
use Humbug\PhpScoper\Logger\ConsoleLogger;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Throwable\Exception\ParsingException;
use Humbug\PhpScoper\Throwable\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * @final
 */
class HandleAddPrefix
{
    /** @internal */
    const PHP_FILE_PATTERN = '/\.php$/';

    private $fileSystem;
    private $scoper;

    public function __construct(Scoper $scoper)
    {
        $this->fileSystem = new Filesystem();
        $this->scoper = $scoper;
    }

    /**
     * Apply prefix to all the code found in the given paths, AKA scope all the files found.
     *
     * @param string        $prefix e.g. 'Foo'
     * @param string[]      $paths List of files to scan (absolute paths)
     * @param string        $output absolute path to the output directory
     * @param ConsoleLogger $logger
     */
    public function __invoke(string $prefix, array $paths, string $output, ConsoleLogger $logger)
    {
        $this->fileSystem->mkdir($output);

        try {
            $files = $this->retrieveFiles($paths, $output);

            $this->scopeFiles($files, $prefix, $logger);
        } catch (Throwable $throwable) {
            $this->fileSystem->remove($output);

            throw $throwable;
        }
    }

    /**
     * @param string[] $paths
     *
     * @return string[] absolute paths
     */
    private function retrieveFiles(array $paths, string $output): array
    {
        $pathsToSearch = [];
        $filesToAppend = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $pathsToSearch[] = $path;
            } elseif (1 === preg_match(self::PHP_FILE_PATTERN, $path)) {
                $filesToAppend[] = $path;
            }
        }

        $finder = new Finder();

        $finder->files()
            ->name(self::PHP_FILE_PATTERN)
            ->in($pathsToSearch)
            ->append($filesToAppend)
            ->sortByName()
        ;

        $files = array_keys(iterator_to_array($finder));

        $commonPath = get_common_path($files);

        return array_map(
            function (string $file) use ($output, $commonPath): string {
                if (false === file_exists($file)) {
                    throw new RuntimeException(
                        sprintf(
                            'Could not find the file "%s".',
                            $file
                        )
                    );
                }

                if (false === is_readable($file)) {
                    throw new RuntimeException(
                        sprintf(
                            'Could not read the file "%s".',
                            $file
                        )
                    );
                }

                return $output.str_replace($commonPath, '', $file);
            },
            $files
        );
    }

    /**
     * @param string[]      $files
     * @param string        $prefix
     * @param ConsoleLogger $logger
     */
    private function scopeFiles(array $files, string $prefix, ConsoleLogger $logger)
    {
        $count = count($files);
        $logger->outputFileCount($count);

        foreach ($files as $file) {
            $this->scopeFile($file, $prefix, $logger);
        }
    }

    private function scopeFile(string $path, string $prefix, ConsoleLogger $logger)
    {
        $fileContent = file_get_contents($path);

        $scoppedContent = $this->scoper->scope($fileContent, $prefix);

        $this->fileSystem->dumpFile($path, $scoppedContent);

        $logger->outputSuccess($path);
    }
}
