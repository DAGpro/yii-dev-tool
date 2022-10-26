<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Stats;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\YiiDevTool\App\Component\Console\OutputManager;
use Yiisoft\YiiDevTool\App\Component\Console\YiiDevToolStyle;
use Yiisoft\YiiDevTool\App\Component\Package\PackageList;
use Yiisoft\YiiDevTool\App\YiiDevToolApplication;

/** @method YiiDevToolApplication getApplication() **/
final class ContributorsCommand extends Command
{
    private ?OutputManager $io = null;
    private ?PackageList $packageList = null;

    protected function configure()
    {
        $this
            ->setName('stats/contributors')
            ->addOption('since', null, InputArgument::OPTIONAL, 'Date and time to check contributors from YYYY-MM-DD.')
            ->setDescription('Displays contributors statistics');

        parent::configure();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new OutputManager(new YiiDevToolStyle($input, $output));
    }

    protected function getIO(): OutputManager
    {
        if ($this->io === null) {
            throw new RuntimeException('IO is not initialized.');
        }

        return $this->io;
    }

    private function initPackageList(): void
    {
        $this->packageList = new PackageList(
            $this
                ->getApplication()
                ->getConfig()
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $since = $input->getOption('since');
        $sinceCommand = '';
        if (!empty($since)) {
            $sinceCommand = " --since=$since";
        }

        $this->initPackageList();

        $installedPackages = $this
            ->getPackageList()
            ->getInstalledAndEnabledPackages();

        $contributors = [];

        foreach ($installedPackages as $installedPackage) {
            $git = $installedPackage
                ->getGitWorkingCopy()
                ->getWrapper();
            $out = $git->git("shortlog -s -e --group=author --group=trailer:co-authored-by$sinceCommand HEAD", $installedPackage->getPath());
            foreach (preg_split('~\R~', $out, -1, PREG_SPLIT_NO_EMPTY) as $line) {
                [$commits, $name] = preg_split('~\t~', $line, -1, PREG_SPLIT_NO_EMPTY);

                if (array_key_exists($name, $contributors)) {
                    $contributors[$name] += (int)$commits;
                } else {
                    $contributors[$name] = (int)$commits;
                }
            }
        }

        arsort($contributors);

        foreach ($contributors as $name => $commits) {
            echo $name . "\n";
        }

        return Command::SUCCESS;
    }

    private function getPackageList(): PackageList
    {
        return $this->packageList;
    }
}
