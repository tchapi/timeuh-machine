<?php

namespace App\Command;

use App\Repository\TrackRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArchivesUpdaterCommand extends Command
{
    private $output;
    private $trackRepository;

    public function __construct(TrackRepository $trackRepository)
    {
        $this->trackRepository = $trackRepository;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('timeuh-machine:update-archives')
            ->setDescription('Update archives views');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>Updating Years highlights.</comment>');
        $this->trackRepository->updateHighlights(TrackRepository::MODE_YEARS);

        $output->writeln('<comment>Updating Months highlights.</comment>');
        $this->trackRepository->updateHighlights(TrackRepository::MODE_MONTHS);

        $output->writeln('<comment>Updating Days highlights.</comment>');
        $this->trackRepository->updateHighlights(TrackRepository::MODE_DAYS);

        $output->writeln('<info>Done, quitting.</info>');
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
