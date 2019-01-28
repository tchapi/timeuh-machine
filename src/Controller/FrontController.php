<?php

namespace App\Controller;

use App\Entity\Track;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Main frontend controller for the website.
 *
 * @author Cyril Chapellier
 */
class FrontController extends AbstractController
{
    /**
     * @Route("/{page}", name="home", requirements={"page" = "\d+"}, defaults={"page" = 1})
     */
    public function homeAction(Request $request, int $page)
    {
        $page = ($page > 1) ? $page : 1;

        $trackRepository = $this->get('doctrine')->getRepository(Track::class);

        $current = $trackRepository->findCurrentlyPlayingTrack();
        $lastTracks = $trackRepository->findNLastTracksExceptCurrentOnPage($this->getParameter('tracks_per_page'), $current, $page);

        if ($request->isXmlHttpRequest()) {
            return $this->render('home.items.html.twig', [
                'lastTracks' => $lastTracks,
            ]);
        } else {
            return $this->render('home.html.twig', [
                'current' => $current,
                'lastTracks' => $lastTracks,
            ]);
        }
    }

    /**
     * @Route("/about", name="about")
     */
    public function aboutAction(Request $request)
    {
        return $this->render('about.html.twig');
    }

    /**
     * @Route("/create/playlist/{year}/{month}/{day}", name="create_playlist", requirements={"year" = "\d+", "month" = "\d+", "day" = "\d+"})
     */
    public function intiateCreatePlaylist(Request $request, int $year = null, int $month = null, ?int $day = null)
    {
        setlocale(LC_TIME, $request->getLocale(), 'fr', 'fr_FR', 'fr_FR@euro', 'fr_FR.utf8', 'fr-FR', 'fra');

        // Get all the spotify tracks id
        $repository = $this->get('doctrine')->getRepository(Track::class);

        if ($month) {
            if ($day) {
                $tracks = $repository->findSpotifyLinksForDay($year, $month, $day);
                $name = $this->get('translator')->trans('playlist.title.day', ['%date%' => strftime('%d/%m/%Y', mktime(0, 0, 0, $month, $day, $year))]);
            } else {
                $tracks = $repository->findSpotifyLinksForMonth($year, $month);
                $name = $this->get('translator')->trans('playlist.title.month', ['%month%' => ucfirst(strftime('%B', mktime(0, 0, 0, $month))), '%year%' => $year]);
            }
        }

        // If there is no spotify track
        if (0 == count($tracks)) {
            $this->addFlash(
                'success',
                $this->get('translator')->trans('playlist.message.message_no_tracks')
            );

            return $this->redirect($request->get('referer') ?: $this->generateUrl('archives'));
        }

        // flatten array
        array_walk($tracks, function (&$item, $key) {
            $item = $item['spotifyLink'];
        });

        // Store the tracks, name of the playlist, playlist image and referer url in session
        $session = $request->getSession();

        $session->set('playlist', [
                'name' => $name,
                'tracks' => $tracks,
            ]);
        $session->set('referer', $request->get('referer') ?: $this->generateUrl('archives'));

        // Launch the login process
        $spotifySession = new \SpotifyWebAPI\Session(
            $this->getParameter('spotify_client_id'),
            $this->getParameter('spotify_client_secret'),
            $this->generateUrl('finalize_playlist', [], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        $options = [
            'scope' => [
                'playlist-modify-public',
            ],
        ];

        return $this->redirect($spotifySession->getAuthorizeUrl($options));
    }

    /**
     * @Route("/finalize/playlist", name="finalize_playlist")
     */
    public function finalizeCreatePlaylist(Request $request)
    {
        // Retrieves the stored session info
        $session = $request->getSession();

        $playlist = $session->get('playlist');
        $referer = $session->get('referer');

        // Retrieve access token
        $spotifySession = new \SpotifyWebAPI\Session(
            $this->getParameter('spotify_client_id'),
            $this->getParameter('spotify_client_secret'),
            $this->generateUrl('finalize_playlist', [], UrlGeneratorInterface::ABSOLUTE_URL)
        );
        $api = new \SpotifyWebAPI\SpotifyWebAPI();

        if (!$request->query->has('code')) {
            return $this->redirectToRoute('home');
        }

        // Request a access token using the code from Spotify
        try {
            $spotifySession->requestAccessToken($request->get('code'));
        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
            return $this->redirectToRoute('home');
        }

        $accessToken = $spotifySession->getAccessToken();

        // Set the access token on the API wrapper
        $api->setAccessToken($accessToken);

        // Get user id
        $me = $api->me();

        // If the playlist exists, find its id
        $playlists = $api->getUserPlaylists($me->id);

        $spotifyPlaylistId = array_reduce($playlists->items, function ($carry, $existingPlaylist) use ($playlist) {
            return ($existingPlaylist->name === $playlist['name']) ? $existingPlaylist->id : $carry;
        }, null);

        if (null !== $spotifyPlaylistId) {
            // Unset tracks that are already in the playlist so we don't create duplicates
            $playlistTracks = $api->getUserPlaylistTracks($me->id, $spotifyPlaylistId);
            foreach ($playlistTracks->items as $track) {
                if (false !== ($key = array_search($track->track->uri, $playlist['tracks']))) {
                    unset($playlist['tracks'][$key]);
                }
            }
            $playlist['tracks'] = array_values($playlist['tracks']);
            if (count($playlist['tracks']) > 0) {
                $this->addFlash(
                    'success',
                    $this->get('translator')->trans('playlist.message.message_update', ['%name%' => $playlist['name']])
                );
            } else {
                $this->addFlash(
                    'success',
                    $this->get('translator')->trans('playlist.message.no_new_tracks', ['%name%' => $playlist['name']])
                );

                return $this->redirect($referer);
            }
        } else {
            // Create playlist
            $createdPlaylist = $api->createUserPlaylist($me->id, [
                'name' => $playlist['name'],
            ]);
            $spotifyPlaylistId = $createdPlaylist->id;
            $this->addFlash(
                'success',
                $this->get('translator')->trans('playlist.message.message_new', ['%name%' => $playlist['name']])
            );
        }

        // Add tracks if any left, in batches of 80 (max is 100 on Spotify, but keep it safe)
        foreach (array_chunk($playlist['tracks'], 80) as $tracks) {
            $api->addUserPlaylistTracks($me->id, $spotifyPlaylistId, $tracks);
        }

        // Redirect to page where the user was, with a popin
        return $this->redirect($referer);
    }

    /**
     * @Route("/archives/{year}/{month}/{day}", name="archives", requirements={"year" = "\d+", "month" = "\d+", "day" = "\d+"})
     */
    public function archivesAction(Request $request, ?int $year = null, ?int $month = null, ?int $day = null)
    {
        setlocale(LC_TIME, $request->getLocale(), 'fr', 'fr_FR', 'fr_FR@euro', 'fr_FR.utf8', 'fr-FR', 'fra');

        if ($year) {
            if ($month) {
                if ($day) {
                    // Get tracks for current year
                    $tracks = $this->get('doctrine')->getRepository(Track::class)->findByDay($year, $month, $day);

                    return $this->render('archives.day.html.twig', [
                        'tracks' => $tracks,
                        'year' => $year,
                        'month' => $month,
                        'monthName' => strftime('%B', mktime(0, 0, 0, $month)),
                        'day' => $day,
                    ]);
                }

                // Get tracks for current month
                $tracks = $this->get('doctrine')->getRepository(Track::class)->findHighlightsByDays($year, $month);

                $days = [];
                foreach ($tracks as $track) {
                    $day = $track['day_n'];
                    if (isset($days[$day])) {
                        $days[$day]['tracks'][] = $track[0];
                    } else {
                        $days[$day] = [
                            'name' => $day,
                            'key' => $day,
                            'tracks' => [$track[0]],
                        ];
                    }
                }

                return $this->render('archives.month.html.twig', [
                    'days' => $days,
                    'year' => $year,
                    'month' => $month,
                    'monthName' => strftime('%B', mktime(0, 0, 0, $month)),
                ]);
            }

            // Get tracks for current year
            $tracks = $this->get('doctrine')->getRepository(Track::class)->findHighlightsByMonths($year);

            $months = [];
            foreach ($tracks as $track) {
                $month = $track['month_n'];
                if (isset($months[$month])) {
                    $months[$month]['tracks'][] = $track[0];
                } else {
                    $months[$month] = [
                        'name' => strftime('%B', mktime(0, 0, 0, $track['month_n'])),
                        'key' => $track['month_n'],
                        'tracks' => [$track[0]],
                    ];
                }
            }

            return $this->render('archives.year.html.twig', [
                'months' => $months,
                'year' => $year,
            ]);
        } else {
            // Get all tracks for all years passed
            $tracks = $this->get('doctrine')->getRepository(Track::class)->findHighlightsByYears();

            // Sort tracks by YEAR properly
            foreach ($tracks as $track) {
                $year = $track['year_n'];
                if (isset($years[$year])) {
                    $years[$year]['tracks'][] = $track[0];
                } else {
                    $years[$year] = [
                        'name' => $year,
                        'tracks' => [$track[0]],
                    ];
                }
            }

            return $this->render('archives.years.html.twig', [
                'years' => $years,
            ]);
        }
    }
}
