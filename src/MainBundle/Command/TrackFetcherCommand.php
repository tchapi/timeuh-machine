<?php

namespace MainBundle\Command;

use MainBundle\Service\ApiService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TrackFetcherCommand extends ContainerAwareCommand
{
    private $output;

    protected function configure()
    {
        $this
            ->setName('archivemeuh:fetch-tracks')
            ->setDescription('Fetch tracks from pseudo API and store result');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $apiService = $this->getContainer()->get('api.service');

        $output->writeln('<info>Fetching data</info>');

        $code = $apiService->getCurrentAndLastTrack();

        if ($code === ApiService::RETURN_FAILURE || $code === ApiService::RETURN_BAD_RESPONSE) {
            $output->writeln('<error>There was a problem fetching data</error>');
        } else if ($code === ApiService::RETURN_SUCCESS) {
            $output->writeln('<info>Data retrieved</info>');   
        }

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
