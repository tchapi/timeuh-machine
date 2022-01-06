<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Track;
use App\Repository\TrackRepository;
use App\Service\ApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TrackFetcherCommand extends Command
{
    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(ApiService $apiService, EntityManagerInterface $em)
    {
        $this->apiService = $apiService;
        $this->em = $em;
        parent::__construct();
    }

    protected function configure(): void
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
            )
            ->addOption(
                'fix-deezer',
                null,
                InputOption::VALUE_NONE,
                'Does not fetch the last tracks but rather fix missing Deezer information in the whole database'
            )
            ->addOption(
                'from-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Restrict fixing missing tracks to the tracks that have been created after the date passed'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $trackRepository = $this->em->getRepository(Track::class);

        if ($input->getOption('from-date')) {
            $fromDate = new \DateTime($input->getOption('from-date'));
            $output->writeln('<comment>Fetching tracks after '.$fromDate->format('d/m/Y').'</comment>');
        }

        if ($input->getOption('fix-missing')) {
            $output->writeln('<info>Fixing missing data in database</info>');
            $tracks = $trackRepository->findMissingTracksFrom(TrackRepository::MISSING_TUNEEFY, $fromDate ?? null);
            $counter = 0;

            foreach ($tracks as $track) {
                $output->write('<comment>Fetching missing data for track #'.$track->getId().' "'.$track->getTitle().'" - "'.$track->getAlbum().'" - "'.$track->getArtist().'"</comment> ... ');

                $result = $this->apiService->getTuneefyLinkAndImage($track);
                if ($result) {
                    $track->setTuneefyLink($result['link']);
                    $track->setSpotifyLink($result['spotifyLink']);
                    if ($result['image']) {
                        $track->setImage($result['image']);
                    }
                    $this->em->flush();
                    $output->writeln('<info>Done.</info>');
                    ++$counter;
                } else {
                    $output->writeln('<error>No result.</error>');
                }
            }

            $output->writeln($counter.' tracks updated — still '.(count($tracks) - $counter).' with missing info');
        } elseif ($input->getOption('fix-spotify')) {
            $output->writeln('<info>Fixing missing Spotify data in database</info>');
            $tracks = $trackRepository->findMissingTracksFrom(TrackRepository::MISSING_SPOTIFY, $fromDate ?? null);
            $counter = 0;

            foreach ($tracks as $track) {
                $output->write('<comment>Fetching missing Spotify data for track #'.$track->getId().' "'.$track->getTitle().'" - "'.$track->getAlbum().'" - "'.$track->getArtist().'"</comment> ... ');

                if (null !== $track->getTuneefyLink()) {
                    $result = $this->apiService->getSpotifyLinkForTuneefyLink($track->getTuneefyLink());
                    if ($result) {
                        $track->setSpotifyLink($result);
                        $this->em->flush();
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
        } elseif ($input->getOption('fix-deezer')) {
            $output->writeln('<info>Fixing missing Deezer data in database</info>');
            $tracks = $trackRepository->findMissingTracksFrom(TrackRepository::MISSING_DEEZER, $fromDate ?? null);
            $counter = 0;

            foreach ($tracks as $track) {
                $output->write('<comment>Fetching missing Deezer data for track #'.$track->getId().' "'.$track->getTitle().'" - "'.$track->getAlbum().'" - "'.$track->getArtist().'"</comment> ... ');

                if (null !== $track->getTuneefyLink()) {
                    $result = $this->apiService->getDeezerLinkForTuneefyLink($track->getTuneefyLink());
                    if ($result) {
                        $track->setDeezerLink($result);
                        $this->em->flush();
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
            $code = $this->apiService->getCurrentAndLastTrack();

            if (ApiService::RETURN_FAILURE === $code || ApiService::RETURN_BAD_RESPONSE === $code) {
                $output->writeln('<error>There was a problem fetching data</error>');
            } elseif (ApiService::RETURN_SUCCESS === $code) {
                $output->writeln('<info>Data retrieved</info>');
            }
        }

        $output->writeln('<info>Done, quitting.</info>');

        return Command::SUCCESS;
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
