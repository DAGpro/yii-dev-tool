<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Packages;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\PackageCommand;
use Yiisoft\YiiDevTool\App\Component\Package\Package;
use Yiisoft\YiiDevTool\App\PackageService;

final class UpdateCommand extends PackageCommand
{
    private array $additionalComposerUpdateOptions = [];

    public function __construct(private PackageService $packageService, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('packages/update')
            ->setAliases(['u'])
            ->setDescription('Pull changes from packages repositories and update composer dependencies')
            ->addOption(
                'no-plugins',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--no-plugins</> during <fg=green;options=bold>composer update</>'
            )
            ->addOption(
                'ignore-platform-reqs',
                null,
                InputOption::VALUE_NONE,
                'Use <fg=green>--ignore-platform-reqs</> during <fg=green;options=bold>composer update</>'
            );

        parent::configure();
    }

    protected function beforeProcessingPackages(InputInterface $input): void
    {
        if ($input->getOption('no-plugins') !== false) {
            $this->additionalComposerUpdateOptions[] = '--no-plugins';
        }
        if ($input->getOption('ignore-platform-reqs') !== false) {
            $this->additionalComposerUpdateOptions[] = '--ignore-platform-reqs';
        }
    }

    protected function processPackage(Package $package): void
    {
        $io = $this->getIO();
        $io->preparePackageHeader($package, 'Updating package {package}');

        if (!$package->isGitRepositoryCloned()) {
            $io->info('Skipped because of package is not installed.');
            return;
        }

        $this->packageService->removeSymbolicLinks($package, $this->getPackageList(), $io);

        if ($this->doesPackageContainErrors($package)) {
            return;
        }

        $this->gitPull($package, $io);

        $this->packageService->composerUpdate(
            $package,
            $this->additionalComposerUpdateOptions,
            $this->getErrorsList(),
            $io
        );

        $this->packageService->createSymbolicLinks($package, $this->getPackageList(), $this->getIO());

        if (!$io->isVerbose()) {
            $io
                ->important()
                ->newLine();
        }
    }

    private function gitPull(Package $package, OutputManager $io): void
    {
        $io
            ->important()
            ->info('Pulling repository');

        $process = new Process(['git', 'pull']);
        $process->setWorkingDirectory($package->getPath());

        $process
            ->setTimeout(null)
            ->run();
        if ($process->isSuccessful()) {
            $io->info($process->getOutput() . $process->getErrorOutput());
            $io->done();
        } else {
            $output = $process->getErrorOutput();

            $io
                ->important()
                ->info($output);
            $io->error([
                'An error occurred during running `git pull`.',
            ]);

            $this->registerPackageError($package, $output, 'running `git pull`');
        }
    }
}