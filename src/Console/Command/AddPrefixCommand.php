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

namespace Humbug\PhpScoper\Console\Command;

use function count;
use Humbug\PhpScoper\Autoload\ScoperAutoloadGenerator;
use Humbug\PhpScoper\Configuration;
use Humbug\PhpScoper\Console\ScoperLogger;
use function Humbug\PhpScoper\get_common_path;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Scoper\ConfigurableScoper;
use Humbug\PhpScoper\Throwable\Exception\ParsingException;
use Humbug\PhpScoper\Whitelist;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use function is_dir;
use function sprintf;

final class AddPrefixCommand extends BaseCommand
{
    private const PATH_ARG = 'paths';
    private const PREFIX_OPT = 'prefix';
    private const OUTPUT_DIR_OPT = 'output-dir';
    private const FORCE_OPT = 'force';
    private const STOP_ON_FAILURE_OPT = 'stop-on-failure';
    private const CONFIG_FILE_OPT = 'config';
    private const CONFIG_FILE_DEFAULT = 'scoper.inc.php';
    private const NO_CONFIG_OPT = 'no-config';

    private $fileSystem;
    private $scoper;
    private $init = false;

    /**
     * @inheritdoc
     */
    public function __construct(Filesystem $fileSystem, Scoper $scoper)
    {
        parent::__construct();

        $this->fileSystem = $fileSystem;
        $this->scoper = new ConfigurableScoper($scoper);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('add-prefix')
            ->setDescription('Goes through all the PHP files found in the given paths to apply the given prefix to namespaces & FQNs.')
            ->addArgument(
                self::PATH_ARG,
                InputArgument::IS_ARRAY,
                'The path(s) to process.'
            )
            ->addOption(
                self::PREFIX_OPT,
                'p',
                InputOption::VALUE_REQUIRED,
                'The namespace prefix to add.'
            )
            ->addOption(
                self::OUTPUT_DIR_OPT,
                'o',
                InputOption::VALUE_REQUIRED,
                'The output directory in which the prefixed code will be dumped.',
                'build'
            )
            ->addOption(
                self::FORCE_OPT,
                'f',
                InputOption::VALUE_NONE,
                'Deletes any existing content in the output directory without any warning.'
            )
            ->addOption(
                self::STOP_ON_FAILURE_OPT,
                's',
                InputOption::VALUE_NONE,
                'Stops on failure.'
            )
            ->addOption(
                self::CONFIG_FILE_OPT,
                'c',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Configuration file. Will use "%s" if found by default.',
                    self::CONFIG_FILE_DEFAULT
                )
            )
            ->addOption(
                self::NO_CONFIG_OPT,
                null,
                InputOption::VALUE_NONE,
                'Do not look for a configuration file.'
            )
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('');

        $this->changeWorkingDirectory($input);

        $this->validatePrefix($input);
        $this->validatePaths($input);

        $config = $this->retrieveConfig($input, $output, $io);
        if(!$this->validateOutputDir($input, $io, $config)) {
            return 1;
        };

        $output = $input->getOption(self::OUTPUT_DIR_OPT);

        if ([] !== $config->getWhitelistedFiles()) {
            $this->scoper = $this->scoper->withWhitelistedFiles(...$config->getWhitelistedFiles());
        }

        $logger = new ScoperLogger(
            $this->getApplication(),
            $io
        );

        $logger->outputScopingStart(
            $config->getPrefix(),
            $input->getArgument(self::PATH_ARG)
        );

        try {
            $this->scopeFiles(
                $config->getPrefix(),
                $config->getFilesWithContents(),
                $output,
                $config->getPatchers(),
                $config->getWhitelist(),
                $input->getOption(self::STOP_ON_FAILURE_OPT),
                $logger
            );
        } catch (Throwable $throwable) {
            $this->fileSystem->remove($output);

            $logger->outputScopingEndWithFailure();

            throw $throwable;
        }

        $logger->outputScopingEnd();

        return 0;
    }

    /**
     * @var callable[]
     */
    private function scopeFiles(
        string $prefix,
        array $filesWithContents,
        string $output,
        array $patchers,
        Whitelist $whitelist,
        bool $stopOnFailure,
        ScoperLogger $logger
    ): void {
        // Creates output directory if does not already exist
        $this->fileSystem->mkdir($output);

        $logger->outputFileCount(count($filesWithContents));

        $vendorDirs = [];
        $commonPath = get_common_path(array_keys($filesWithContents));

        foreach ($filesWithContents as [$inputFilePath, $inputContents]) {
            $outputFilePath = $output.str_replace($commonPath, '', $inputFilePath);

            $pattern = '~((?:.*)\\'.DIRECTORY_SEPARATOR.'vendor)\\'.DIRECTORY_SEPARATOR.'.*~';
            if (preg_match($pattern, $outputFilePath, $matches)) {
                $vendorDirs[$matches[1]] = true;
            }

            $this->scopeFile(
                $inputFilePath,
                $inputContents,
                $outputFilePath,
                $prefix,
                $patchers,
                $whitelist,
                $stopOnFailure,
                $logger
            );
        }

        $vendorDirs = array_keys($vendorDirs);

        usort(
            $vendorDirs,
            static function ($a, $b) {
                return strlen($b) <=> strlen($a);
            }
        );

        $vendorDir = (0 === count($vendorDirs)) ? null : $vendorDirs[0];

        if (null !== $vendorDir) {
            $autoload = (new ScoperAutoloadGenerator($whitelist))->dump();

            $this->fileSystem->dumpFile($vendorDir.'/scoper-autoload.php', $autoload);
        }
    }

    /**
     * @param callable[] $patchers
     */
    private function scopeFile(
        string $inputFilePath,
        string $inputContents,
        string $outputFilePath,
        string $prefix,
        array $patchers,
        Whitelist $whitelist,
        bool $stopOnFailure,
        ScoperLogger $logger
    ): void {
        try {
            $scoppedContent = $this->scoper->scope($inputFilePath, $inputContents, $prefix, $patchers, $whitelist);
        } catch (Throwable $throwable) {
            $exception = new ParsingException(
                sprintf(
                    'Could not parse the file "%s".',
                    $inputFilePath
                ),
                0,
                $throwable
            );

            if ($stopOnFailure) {
                throw $exception;
            }

            $logger->outputWarnOfFailure($inputFilePath, $exception);

            $scoppedContent = file_get_contents($inputFilePath);
        }

        $this->fileSystem->dumpFile($outputFilePath, $scoppedContent);

        if (false === isset($exception)) {
            $logger->outputSuccess($inputFilePath);
        }
    }

    private function validatePrefix(InputInterface $input): void
    {
        $prefix = $input->getOption(self::PREFIX_OPT);

        if (null !== $prefix && 1 === preg_match('/(?<prefix>.*?)\\\\*$/', $prefix, $matches)) {
            $prefix = $matches['prefix'];
        }

        $input->setOption(self::PREFIX_OPT, $prefix);
    }

    private function validatePaths(InputInterface $input): void
    {
        $cwd = getcwd();
        $fileSystem = $this->fileSystem;

        $paths = array_map(
            static function (string $path) use ($cwd, $fileSystem) {
                if (false === $fileSystem->isAbsolutePath($path)) {
                    return $cwd.DIRECTORY_SEPARATOR.$path;
                }

                return $path;
            },
            $input->getArgument(self::PATH_ARG)
        );

        $input->setArgument(self::PATH_ARG, $paths);
    }

    /**
     * @return bool True if the command execution must continue after the function returns, false otherwise
     */
    private function validateOutputDir(InputInterface $input, OutputStyle $io, Configuration $config): bool
    {
        $outputDir = $input->getOption(self::OUTPUT_DIR_OPT);

        if (false === $this->fileSystem->isAbsolutePath($outputDir)) {
            $outputDir = getcwd().DIRECTORY_SEPARATOR.$outputDir;
        }

        $input->setOption(self::OUTPUT_DIR_OPT, $outputDir);

        if (false === $this->fileSystem->exists($outputDir)) {
            return true;
        }

        if (false === is_writable($outputDir)) {
            throw new RuntimeException(
                sprintf(
                    'Expected "<comment>%s</comment>" to be writeable.',
                    $outputDir
                )
            );
        }

        if ($input->getOption(self::FORCE_OPT)) {
            $onExistingOutputDirBehavior = 'overwrite';
        } else {
            $onExistingOutputDirBehavior = $config->getOnExistingOutputDir();
        }

        if ('overwrite' === $onExistingOutputDirBehavior) {
            $this->fileSystem->remove($outputDir);

            return true;
        }

        if ('abort' === $onExistingOutputDirBehavior) {
            $io->writeln(
                sprintf(
                    '%s "<comment>%s</comment>" already exists, aborting.',
                    is_dir($outputDir) ? 'Directory' : 'File',
                    $outputDir
                )
            );
            return false;
        }

        if (false === is_dir($outputDir)) {
            $canDeleteFile = $io->confirm(
                sprintf(
                    'Expected "<comment>%s</comment>" to be a directory but found a file instead. It will be '
                    .'removed, do you wish to proceed?',
                    $outputDir
                ),
                false
            );

            if (false === $canDeleteFile) {
                return false;
            }

            $this->fileSystem->remove($outputDir);
        } else {
            $canDeleteFile = $io->confirm(
                sprintf(
                    'The output directory "<comment>%s</comment>" already exists. Continuing will erase its'
                    .' content, do you wish to proceed?',
                    $outputDir
                ),
                false
            );

            if (false === $canDeleteFile) {
                return false;
            }

            $this->fileSystem->remove($outputDir);

            return true;
        }
    }

    private function retrieveConfig(InputInterface $input, OutputInterface $output, OutputStyle $io): Configuration
    {
        $prefix = $input->getOption(self::PREFIX_OPT);

        if ($input->getOption(self::NO_CONFIG_OPT)) {
            $io->writeln(
                'Loading without configuration file.',
                OutputInterface::VERBOSITY_DEBUG
            );

            $config = Configuration::load();

            if (null !== $prefix) {
                $config = $config->withPrefix($prefix);
            }

            if (null === $config->getPrefix()) {
                $config = $config->withPrefix($this->generateRandomPrefix());
            }

            return $this->retrievePaths($input, $config);
        }

        $configFile = $input->getOption(self::CONFIG_FILE_OPT);

        if (null === $configFile) {
            $configFile = $this->makeAbsolutePath(self::CONFIG_FILE_DEFAULT);

            if (false === $this->init && false === file_exists($configFile)) {
                $this->init = true;

                $initCommand = $this->getApplication()->find('init');

                $initInput = new StringInput('');
                $initInput->setInteractive($input->isInteractive());

                $initCommand->run($initInput, $output);

                $io->writeln(
                    sprintf(
                        'Config file "<comment>%s</comment>" not found. Skipping.',
                        $configFile
                    ),
                    OutputInterface::VERBOSITY_DEBUG
                );

                return self::retrieveConfig($input, $output, $io);
            }

            if ($this->init) {
                $configFile = null;
            }
        } else {
            $configFile = $this->makeAbsolutePath($configFile);
        }

        if (null === $configFile) {
            $io->writeln(
                'Loading without configuration file.',
                OutputInterface::VERBOSITY_DEBUG
            );
        } elseif (false === file_exists($configFile)) {
            throw new RuntimeException(
                sprintf(
                    'Could not find the configuration file "%s".',
                    $configFile
                )
            );
        } else {
            $io->writeln(
                sprintf(
                    'Using the configuration file "%s".',
                    $configFile
                ),
                OutputInterface::VERBOSITY_DEBUG
            );
        }

        $config = Configuration::load($configFile);
        $config = $this->retrievePaths($input, $config);

        if (null !== $prefix) {
            $config = $config->withPrefix($prefix);
        }

        if (null === $config->getPrefix()) {
            $config = $config->withPrefix($this->generateRandomPrefix());
        }

        return $config;
    }

    private function retrievePaths(InputInterface $input, Configuration $config): Configuration
    {
        // Checks if there is any path included and if note use the current working directory as the include path
        $paths = $input->getArgument(self::PATH_ARG);

        if (0 === count($paths) && 0 === count($config->getFilesWithContents())) {
            $paths = [getcwd()];
        }

        return $config->withPaths($paths);
    }

    private function makeAbsolutePath(string $path): string
    {
        if (false === $this->fileSystem->isAbsolutePath($path)) {
            $path = getcwd().DIRECTORY_SEPARATOR.$path;
        }

        return $path;
    }

    private function generateRandomPrefix(): string
    {
        return '_PhpScoper'.bin2hex(random_bytes(6));
    }
}
