<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Track;
use App\Service\DeezerApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for the playlist functionality.
 *
 * @author Cyril Chapellier
 */
final class PlaylistController extends AbstractController
{
    public const PROVIDER_DEEZER = 'deezer';
    public const PROVIDER_SPOTIFY = 'spotify';

    #[Route('/create/playlist/{provider}/{year}/{month}/{day}', name: 'create_playlist', requirements: ['provider' => 'spotify|deezer', 'year' => "\d+", 'month' => "\d+", 'day' => "\d+"])]
    public function intiateCreatePlaylist(EntityManagerInterface $em, TranslatorInterface $translator, Request $request, string $provider, ?int $year = null, ?int $month = null, ?int $day = null): Response
    {
        if (null === $year || null === $month) {
            throw $this->createNotFoundException('A year and a month are required to create a playlist.');
        }

        $formatter = new \IntlDateFormatter(
            'fr_FR', // Could be $request->getLocale()
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'Europe/Paris',
            \IntlDateFormatter::TRADITIONAL
        );

        // Get all the tracks id
        $repository = $em->getRepository(Track::class);
        $provider = strtolower($provider);

        if ($day) {
            $formatter->setPattern('dd/MM/yyyy');
            $tracks = $repository->findProviderLinksForDay($provider, $year, $month, $day);
            $name = $translator->trans('playlist.title.day', ['%date%' => $formatter->format(mktime(0, 0, 0, $month, $day, $year))]);
        } else {
            $formatter->setPattern('MMMM');
            $tracks = $repository->findProviderLinksForMonth($provider, $year, $month);
            $name = $translator->trans('playlist.title.month', ['%month%' => ucfirst($formatter->format(mktime(0, 0, 0, $month))), '%year%' => $year]);
        }

        // If there is no track
        if (0 === count($tracks)) {
            $this->addFlash(
                'success',
                $translator->trans('playlist.message.message_no_tracks')
            );

            return $this->redirect($request->get('referer') ?: $this->generateUrl('archives'));
        }

        // flatten array
        array_walk($tracks, static function (&$item) use ($provider): void {
            $item = $item[$provider.'Link'];
        });

        $tracks = array_unique($tracks);

        // Store the tracks, name of the playlist, playlist image and referer url in session
        $session = $request->getSession();

        $session->set('playlist', [
            'name' => $name,
            'tracks' => $tracks,
        ]);
        $session->set('referer', $request->get('referer') ?: $this->generateUrl('archives'));

        $finalizeUrl = $this->generateUrl('finalize_playlist', ['provider' => $provider], UrlGeneratorInterface::ABSOLUTE_URL);

        switch ($provider) {
            case self::PROVIDER_SPOTIFY:
                $wrapper = new \SpotifyWebAPI\Session(
                    $this->getStringParameter('spotify_client_id'),
                    $this->getStringParameter('spotify_client_secret'),
                    $finalizeUrl
                );

                $options = [
                    'scope' => [
                        'playlist-modify-public',
                    ],
                ];
                break;

            case self::PROVIDER_DEEZER:
                $wrapper = new DeezerApi(
                    $this->getStringParameter('deezer_app_id'),
                    $this->getStringParameter('deezer_secret'),
                    $finalizeUrl
                );

                $options = [
                    'perms' => 'manage_library',
                ];
                break;

            default:
                throw $this->createNotFoundException('Unknown provider '.$provider);
        }

        return $this->redirect($wrapper->getAuthorizeUrl($options));
    }

    #[Route('/finalize/playlist/{provider}', name: 'finalize_playlist', requirements: ['provider' => 'spotify|deezer'])]
    public function finalizeCreatePlaylist(TranslatorInterface $translator, Request $request, string $provider): Response
    {
        // Retrieves the stored session info
        $session = $request->getSession();

        $playlist = $session->get('playlist');
        $referer = $session->get('referer');
        $finalizeUrl = $this->generateUrl('finalize_playlist', ['provider' => $provider], UrlGeneratorInterface::ABSOLUTE_URL);

        switch ($provider) {
            case self::PROVIDER_SPOTIFY:
                $wrapper = new \SpotifyWebAPI\Session(
                    $this->getStringParameter('spotify_client_id'),
                    $this->getStringParameter('spotify_client_secret'),
                    $finalizeUrl
                );
                $api = new \SpotifyWebAPI\SpotifyWebAPI();
                break;

            case self::PROVIDER_DEEZER:
                $wrapper = new DeezerApi(
                    $this->getStringParameter('deezer_app_id'),
                    $this->getStringParameter('deezer_secret'),
                    $finalizeUrl
                );
                $api = $wrapper;
                break;

            default:
                throw $this->createNotFoundException('Unknown provider '.$provider);
        }

        if (!$request->query->has('code')) {
            return $this->redirectToRoute('home');
        }

        // Request a access token using the code
        try {
            $wrapper->requestAccessToken($request->get('code'));
        } catch (\Exception $e) {
            return $this->redirectToRoute('home');
        }

        if ($api instanceof DeezerApi) {
            $playlists = $api->getUserPlaylists();
        } else {
            if (!$wrapper instanceof \SpotifyWebAPI\Session) {
                throw new \LogicException('The Spotify API requires a Spotify session.');
            }

            // Set the access token on the API wrapper
            $api->setAccessToken($wrapper->getAccessToken());

            // Get user id
            $me = $api->me();

            // If the playlist exists, find its id
            $playlists = $api->getUserPlaylists($me->id);
        }

        $providerPlaylistId = array_reduce($playlists->items, static function ($carry, $existingPlaylist) use ($playlist) {
            return $existingPlaylist->name === $playlist['name'] ? $existingPlaylist->id : $carry;
        }, null);

        if (null !== $providerPlaylistId) {
            // Unset tracks that are already in the playlist so we don't create duplicates
            $playlistTracks = $api->getPlaylistTracks($providerPlaylistId);
            foreach ($playlistTracks->items as $track) {
                if (false !== ($key = array_search($track->track->uri, $playlist['tracks']))) {
                    unset($playlist['tracks'][$key]);
                }
            }
            $playlist['tracks'] = array_values($playlist['tracks']);
            if (count($playlist['tracks']) > 0) {
                $this->addFlash(
                    'success',
                    $translator->trans('playlist.message.message_update', ['%name%' => $playlist['name']])
                );
            } else {
                $this->addFlash(
                    'success',
                    $translator->trans('playlist.message.no_new_tracks', ['%name%' => $playlist['name']])
                );

                return $this->redirect($referer);
            }
        } else {
            // Create playlist
            $createdPlaylist = $api->createPlaylist([
                'name' => $playlist['name'],
            ]);
            $providerPlaylistId = $createdPlaylist->id;
            $this->addFlash(
                'success',
                $translator->trans('playlist.message.message_new', ['%name%' => $playlist['name']])
            );
        }

        // Add tracks if any left, in batches of 80 (max is 100 on Spotify, but keep it safe)
        foreach (array_chunk($playlist['tracks'], 80) as $tracks) {
            $api->addPlaylistTracks($providerPlaylistId, $tracks);
        }

        // Redirect to page where the user was, with a popin
        return $this->redirect($referer);
    }

    private function getStringParameter(string $name): string
    {
        $value = $this->getParameter($name);

        if (!is_string($value)) {
            throw new \LogicException(sprintf('Parameter "%s" must be a string, got %s.', $name, get_debug_type($value)));
        }

        return $value;
    }
}
