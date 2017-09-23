<?php

namespace MainBundle\Command;

use MainBundle\Entity\Track;
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
            ->setName('timeuh-machine:fetch-tracks')
            ->setDescription('Fetch tracks from pseudo API and store result')
            ->addOption(
                'fix-missing',
                null,
                InputOption::VALUE_NONE,
                'Does not fetch the last tracks but rather fix missing tuneefy information in the whole database'
            )
            ->addOption(
                'fix-spotify',
                null,
                InputOption::VALUE_NONE,
                'Does not fetch the last tracks but rather fix missing Spotify information in the whole database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $apiService = $this->getContainer()->get('api.service');

        if ($input->getOption('fix-missing')) {
            $output->writeln('<info>Fixing missing data in database</info>');
            $tracks = $this->getContainer()->get('doctrine')->getRepository(Track::class)->findBy(['tuneefyLink' => null, 'valid' => 1]);
            $counter = 0;

            foreach ($tracks as $track) {
                $output->write('<comment>Fetching missing data for track #'.$track->getId().' "'.$track->getTitle().'" - "'.$track->getAlbum().'" - "'.$track->getArtist().'"</comment> ... ');

                $result = $apiService->getTuneefyLinkAndImage($track);
                if ($result) {
                    $track->setTuneefyLink($result['link']);
                    $track->setSpotifyLink($result['spotifyLink']);
                    if ($result['image']) {
                        $track->setImage($result['image']);
                    }
                    $em->flush();
                    $output->writeln('<info>Done.</info>');
                    ++$counter;
                } else {
                    $output->writeln('<error>No result.</error>');
                }
            }

            $output->writeln($counter.' tracks updated — still '.(count($tracks) - $counter).' with missing info');
        } else if ($input->getOption('fix-spotify')) {
            $output->writeln('<info>Fixing missing Spotify data in database</info>');
            $tracks = $this->getContainer()->get('doctrine')->getRepository(Track::class)->findBy(['spotifyLink' => null, 'valid' => 1]);
            $counter = 0;

            foreach ($tracks as $track) {
                $output->write('<comment>Fetching missing Spotify data for track #'.$track->getId().' "'.$track->getTitle().'" - "'.$track->getAlbum().'" - "'.$track->getArtist().'"</comment> ... ');

                if ($track->getTuneefyLink() != null) {
                    $result = $apiService->getSpotifyLinkForTuneefyLink($track->getTuneefyLink());
                    if ($result) {
                        $track->setSpotifyLink($result);
                        $em->flush();
                        $output->writeln('<info>Done.</info>');
                        ++$counter;
                    } else {
                        $output->writeln('<error>No result.</error>');
                    }
                } else {
                    $output->writeln('<comment>No tuneefy link, skipping.</comment>');
                }
            }

            $output->writeln($counter.' tracks updated — still '.(count($tracks) - $counter).' with missing info');
        } else {
            $output->writeln('<info>Fetching data from API</info>');
            $code = $apiService->getCurrentAndLastTrack();

            if (ApiService::RETURN_FAILURE === $code || ApiService::RETURN_BAD_RESPONSE === $code) {
                $output->writeln('<error>There was a problem fetching data</error>');
            } elseif (ApiService::RETURN_SUCCESS === $code) {
                $output->writeln('<info>Data retrieved</info>');
            }
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
