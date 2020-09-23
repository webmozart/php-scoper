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

use function array_key_exists;
use function array_unshift;
use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_exists;
use function gettype;
use Humbug\PhpScoper\Patcher\SymfonyPatcher;
use InvalidArgumentException;
use function is_array;
use function is_bool;
use function is_file;
use function is_link;
use function is_string;
use Iterator;
use function readlink;
use function realpath;
use RuntimeException;
use SplFileInfo;
use function sprintf;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final class Configuration
{
    private const PREFIX_KEYWORD = 'prefix';
    private const WHITELISTED_FILES_KEYWORD = 'files-whitelist';
    private const FINDER_KEYWORD = 'finders';
    private const PATCHERS_KEYWORD = 'patchers';
    private const WHITELIST_KEYWORD = 'whitelist';
    private const WHITELIST_GLOBAL_CONSTANTS_KEYWORD = 'whitelist-global-constants';
    private const WHITELIST_GLOBAL_CLASSES_KEYWORD = 'whitelist-global-classes';
    private const WHITELIST_GLOBAL_FUNCTIONS_KEYWORD = 'whitelist-global-functions';
    private const OUTPUT_DIR_KEYWORD = 'output-dir';

    private const KEYWORDS = [
        self::PREFIX_KEYWORD,
        self::WHITELISTED_FILES_KEYWORD,
        self::FINDER_KEYWORD,
        self::PATCHERS_KEYWORD,
        self::WHITELIST_KEYWORD,
        self::WHITELIST_GLOBAL_CONSTANTS_KEYWORD,
        self::WHITELIST_GLOBAL_CLASSES_KEYWORD,
        self::WHITELIST_GLOBAL_FUNCTIONS_KEYWORD,
        self::OUTPUT_DIR_KEYWORD
    ];

    private $path;
    private $prefix;
    private $filesWithContents;
    private $patchers;
    private $whitelist;
    private $whitelistedFiles;
    private $outputDir;

    /**
     * @param string|null $path  Absolute path to the configuration file.
     * @param string[]    $paths List of paths to append besides the one configured
     *
     * @return self
     */
    public static function load(string $path = null, array $paths = []): self
    {
        if (null === $path) {
            $config = [];
        } else {
            if (false === (new Filesystem())->isAbsolutePath($path)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected the path of the configuration file to load to be an absolute path, got "%s" '
                        .'instead',
                        $path
                    )
                );
            }

            if (false === file_exists($path)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected the path of the configuration file to exists but the file "%s" could not be '
                        .'found',
                        $path
                    )
                );
            }

            if (false === is_file($path) && false === (is_link($path) && is_file(readlink($path)))) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected the path of the configuration file to be a file but "%s" appears to be a '
                        .'directory.',
                        $path
                    )
                );
            }

            $config = include $path;

            if (false === is_array($config)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected configuration to be an array, found "%s" instead.',
                        gettype($config)
                    )
                );
            }
        }

        self::validateConfigKeys($config);

        $prefix = self::retrievePrefix($config);

        $whitelistedFiles = null === $path ? [] : self::retrieveWhitelistedFiles(dirname($path), $config);

        $patchers = self::retrievePatchers($config);

        array_unshift($patchers, new SymfonyPatcher());

        $whitelist = self::retrieveWhitelist($config);

        $finders = self::retrieveFinders($config);
        $filesFromPaths = self::retrieveFilesFromPaths($paths);
        $filesWithContents = self::retrieveFilesWithContents(chain($filesFromPaths, ...$finders));

        if (false === array_key_exists(self::OUTPUT_DIR_KEYWORD, $config)) {
            $outputDir = null;
        } else {
            $outputDir = $config[ self::OUTPUT_DIR_KEYWORD ];
        }

        return new self($path, $prefix, $filesWithContents, $patchers, $whitelist, $whitelistedFiles, $outputDir);
    }

    /**
     * @param string|null $path              Absolute path to the configuration file loaded.
     * @param string|null $prefix            The prefix applied.
     * @param string[][]  $filesWithContents Array of tuple with the first argument being the file path and the second its contents
     * @param callable[]  $patchers          List of closures which can alter the content of the files being
     *                                       scoped.
     * @param Whitelist   $whitelist         List of classes that will not be scoped.
     *                                       returning a boolean which if `true` means the class should be scoped
     *                                       (i.e. is ignored) or scoped otherwise.
     * @param string[]    $whitelistedFiles  List of absolute paths of files to completely ignore
     */
    private function __construct(
        ?string $path,
        ?string $prefix,
        array $filesWithContents,
        array $patchers,
        Whitelist $whitelist,
        array $whitelistedFiles,
        ?string $outputDir
    ) {
        $this->path = $path;
        $this->prefix = $prefix;
        $this->filesWithContents = $filesWithContents;
        $this->patchers = $patchers;
        $this->whitelist = $whitelist;
        $this->whitelistedFiles = $whitelistedFiles;
        $this->outputDir = $outputDir;
    }

    public function withPaths(array $paths): self
    {
        $filesWithContents = self::retrieveFilesWithContents(
            chain(
                self::retrieveFilesFromPaths(
                    array_unique($paths)
                )
            )
        );

        return new self(
            $this->path,
            $this->prefix,
            array_merge($this->filesWithContents, $filesWithContents),
            $this->patchers,
            $this->whitelist,
            $this->whitelistedFiles,
            $this->outputDir
        );
    }

    public function withPrefix(?string $prefix): self
    {
        $prefix = self::retrievePrefix([self::PREFIX_KEYWORD => $prefix]);

        return new self(
            $this->path,
            $prefix,
            $this->filesWithContents,
            $this->patchers,
            $this->whitelist,
            $this->whitelistedFiles,
            $this->outputDir
        );
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getFilesWithContents(): array
    {
        return $this->filesWithContents;
    }

    /**
     * @return callable[]
     */
    public function getPatchers(): array
    {
        return $this->patchers;
    }

    public function getWhitelist(): Whitelist
    {
        return $this->whitelist;
    }

    /**
     * @return string[]
     */
    public function getWhitelistedFiles(): array
    {
        return $this->whitelistedFiles;
    }

    public function getOutputDir(): ?string {
    	return $this->outputDir;
    }

    private static function validateConfigKeys(array $config): void
    {
        array_map(
            [self::class, 'validateConfigKey'],
            array_keys($config)
        );
    }

    private static function validateConfigKey(string $key): void
    {
        if (false === in_array($key, self::KEYWORDS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid configuration key value "%s" found.',
                    $key
                )
            );
        }
    }

    /**
     * If the prefix is set to null in the config file/argument then a random prefix is being used. However if set to
     * empty, the configuration will use a null prefix.
     *
     * TL:DR; setting the prefix is a big confusing because it is not properly split in "set prefix" & prefix strategy".
     */
    private static function retrievePrefix(array $config): ?string
    {
        $prefix = $config[self::PREFIX_KEYWORD] ?? null;

        if (null === $prefix) {
            return null;
        }

        $prefix = trim($prefix);

        return '' === $prefix ? null : $prefix;
    }

    private static function retrievePatchers(array $config): array
    {
        if (false === array_key_exists(self::PATCHERS_KEYWORD, $config)) {
            return [];
        }

        $patchers = $config[self::PATCHERS_KEYWORD];

        if (false === is_array($patchers)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected patchers to be an array of callables, found "%s" instead.',
                    gettype($patchers)
                )
            );
        }

        foreach ($patchers as $index => $patcher) {
            if (is_callable($patcher)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected patchers to be an array of callables, the "%d" element is not.',
                    $index
                )
            );
        }

        return $patchers;
    }

    private static function retrieveWhitelist(array $config): Whitelist
    {
        if (false === array_key_exists(self::WHITELIST_KEYWORD, $config)) {
            $whitelist = [];
        } else {
            $whitelist = $config[self::WHITELIST_KEYWORD];

            if (false === is_array($whitelist)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected whitelist to be an array of strings, found "%s" instead.',
                        gettype($whitelist)
                    )
                );
            }

            foreach ($whitelist as $index => $className) {
                if (is_string($className)) {
                    continue;
                }

                throw new InvalidArgumentException(
                    sprintf(
                        'Expected whitelist to be an array of string, the "%d" element is not.',
                        $index
                    )
                );
            }
        }

        if (false === array_key_exists(self::WHITELIST_GLOBAL_CONSTANTS_KEYWORD, $config)) {
            $whitelistGlobalConstants = true;
        } else {
            $whitelistGlobalConstants = $config[self::WHITELIST_GLOBAL_CONSTANTS_KEYWORD];

            if (false === is_bool($whitelistGlobalConstants)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected %s to be a boolean, found "%s" instead.',
                        self::WHITELIST_GLOBAL_CONSTANTS_KEYWORD,
                        gettype($whitelistGlobalConstants)
                    )
                );
            }
        }

        if (false === array_key_exists(self::WHITELIST_GLOBAL_CLASSES_KEYWORD, $config)) {
            $whitelistGlobalClasses = true;
        } else {
            $whitelistGlobalClasses = $config[self::WHITELIST_GLOBAL_CLASSES_KEYWORD];

            if (false === is_bool($whitelistGlobalClasses)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected %s to be a boolean, found "%s" instead.',
                        self::WHITELIST_GLOBAL_CLASSES_KEYWORD,
                        gettype($whitelistGlobalClasses)
                    )
                );
            }
        }

        if (false === array_key_exists(self::WHITELIST_GLOBAL_FUNCTIONS_KEYWORD, $config)) {
            $whitelistGlobalFunctions = true;
        } else {
            $whitelistGlobalFunctions = $config[self::WHITELIST_GLOBAL_FUNCTIONS_KEYWORD];

            if (false === is_bool($whitelistGlobalFunctions)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected %s to be a boolean, found "%s" instead.',
                        self::WHITELIST_GLOBAL_FUNCTIONS_KEYWORD,
                        gettype($whitelistGlobalFunctions)
                    )
                );
            }
        }

        return Whitelist::create($whitelistGlobalConstants, $whitelistGlobalClasses, $whitelistGlobalFunctions, ...$whitelist);
    }

    /**
     * @return string[] Absolute paths
     */
    private static function retrieveWhitelistedFiles(string $dirPath, array $config): array
    {
        if (false === array_key_exists(self::WHITELISTED_FILES_KEYWORD, $config)) {
            return [];
        }

        $whitelistedFiles = $config[self::WHITELISTED_FILES_KEYWORD];

        if (false === is_array($whitelistedFiles)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected whitelisted files to be an array of strings, found "%s" instead.',
                    gettype($whitelistedFiles)
                )
            );
        }

        foreach ($whitelistedFiles as $index => $file) {
            if (false === is_string($file)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Expected whitelisted files to be an array of string, the "%d" element is not.',
                        $index
                    )
                );
            }

            if (false === (new Filesystem())->isAbsolutePath($file)) {
                $file = $dirPath.DIRECTORY_SEPARATOR.$file;
            }

            $whitelistedFiles[$index] = realpath($file);
        }

        return array_filter($whitelistedFiles);
    }

    private static function retrieveFinders(array $config): array
    {
        if (false === array_key_exists(self::FINDER_KEYWORD, $config)) {
            return [];
        }

        $finders = $config[self::FINDER_KEYWORD];

        if (false === is_array($finders)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected finders to be an array of "%s", found "%s" instead.',
                    Finder::class,
                    gettype($finders)
                )
            );
        }

        foreach ($finders as $index => $finder) {
            if ($finder instanceof Finder) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected finders to be an array of "%s", the "%d" element is not.',
                    Finder::class,
                    $index
                )
            );
        }

        return $finders;
    }

    /**
     * @param string[] $paths
     *
     * @return iterable
     */
    private static function retrieveFilesFromPaths(array $paths): iterable
    {
        if ([] === $paths) {
            return [];
        }

        $pathsToSearch = [];
        $filesToAppend = [];

        foreach ($paths as $path) {
            if (false === file_exists($path)) {
                throw new RuntimeException(
                    sprintf(
                        'Could not find the file "%s".',
                        $path
                    )
                );
            }

            if (is_dir($path)) {
                $pathsToSearch[] = $path;
            } else {
                $filesToAppend[] = $path;
            }
        }

        $finder = new Finder();

        $finder->files()
            ->in($pathsToSearch)
            ->append($filesToAppend)
            ->filter(static function (SplFileInfo $fileInfo): ?bool {
                if ($fileInfo->isLink()) {
                    return false;
                }

                return null;
            })
            ->sortByName()
        ;

        return $finder;
    }

    /**
     * @param Iterator $files
     *
     * @return string[][] Array of tuple with the first argument being the file path and the second its contents
     */
    private static function retrieveFilesWithContents(Iterator $files): array
    {
        return array_reduce(
            iterator_to_array($files, false),
            static function (array $files, SplFileInfo $fileInfo): array {
                $file = $fileInfo->getRealPath();

                if (false === $file) {
                    throw new RuntimeException(
                        sprintf(
                            'Could not find the file "%s".',
                            (string) $fileInfo
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

                $files[$fileInfo->getRealPath()] = [$fileInfo->getRealPath(), file_get_contents($file)];

                return $files;
            },
            []
        );
    }
}
