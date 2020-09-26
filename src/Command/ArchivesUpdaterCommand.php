<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TrackRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ArchivesUpdaterCommand extends Command
{
    const STARTING_YEAR = 2017;

    private $trackRepository;

    public function __construct(TrackRepository $trackRepository)
    {
        $this->trackRepository = $trackRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('timeuh-machine:update-archives')
            ->setDescription('Update archives views')
            ->addOption(
                'from-all-time',
                null,
                InputOption::VALUE_NONE,
                'Updates archives for all years since the start (2017) and not only for the current year'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $thisYear = (new \DateTime())->format('Y');
        $thisMonth = (new \DateTime())->format('m');

        if ($input->getOption('from-all-time')) {
            foreach (range(self::STARTING_YEAR, $thisYear) as $year) {
                $output->writeln('<info>For '.$year.':</info>');
                $output->writeln('<comment>  - Updating Years highlights.</comment>');
                $this->trackRepository->updateHighlights(TrackRepository::MODE_YEARS, $year);

                $output->writeln('<comment>  - Updating Months highlights.</comment>');
                $this->trackRepository->updateHighlights(TrackRepository::MODE_MONTHS, $year);

                $section = $output->section();
                $section->writeln('<comment>  - Updating Days highlights : Month 1/12.</comment>');
                foreach (range(1, 12) as $month) {
                    $section->overwrite('<comment>  - Updating Days highlights : Month '.$month.'/12.</comment>');
                    $this->trackRepository->updateHighlights(TrackRepository::MODE_DAYS, $year, $month);
                }
            }
        } else {
            $output->writeln('<comment>Updating Years highlights for '.$thisYear.'.</comment>');
            $this->trackRepository->updateHighlights(TrackRepository::MODE_YEARS, $thisYear);

            $output->writeln('<comment>Updating Months highlights for '.$thisYear.'.</comment>');
            $this->trackRepository->updateHighlights(TrackRepository::MODE_MONTHS, $thisYear);

            $output->writeln('<comment>Updating Days highlights for '.$thisYear.'/'.$thisMonth.'.</comment>');
            $this->trackRepository->updateHighlights(TrackRepository::MODE_DAYS, $thisYear, $thisMonth);
        }

        $output->writeln('<info>Done, quitting.</info>');

        return 1;
    }

    protected function runCommand($command, InputInterface $input, OutputInterface $output)
    {
        $this
            ->getApplication()
            ->find($command)
            ->run($input, $output);

        return $this;
    }
}
