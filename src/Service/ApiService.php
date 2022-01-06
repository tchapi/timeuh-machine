<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Track;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The service that calls the pseudo RadioMeuh API.
 *
 * @author Cyril Chapellier
 */
final class ApiService
{
    public const RETURN_SUCCESS = 1;
    public const RETURN_FAILURE = 0;
    public const RETURN_BAD_RESPONSE = -1;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Set ParameterBag.
     */
    public function setParameterBag(ParameterBagInterface $params): void
    {
        $this->parameterBag = $params;
    }

    /**
     * Retrieves the two current and last tracks from the API
     * and stores them.
     */
    public function getCurrentAndLastTrack(): int
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [],
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT_MS => 10000,
            CURLOPT_CONNECTTIMEOUT_MS => 10000,
            CURLOPT_URL => $this->getParameter('track_endpoint'),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return self::RETURN_FAILURE;
        }

        // Structure content
        try {
            $data = json_decode($response);
        } catch (Exception $e) {
            return self::RETURN_BAD_RESPONSE;
        }

        $tracks = $data->result;
        unset($tracks->log);

        $tracksRepository = $this->em->getRepository(Track::class);

        foreach ($tracks as $track) {
            // The starting time of the song is a unique identifier so we can rely
            // on that to see if we've hit the same playlist or not in this call.
            // BUT we have to be cautious since only the HH:mm:ss is indicated in
            // the API result.
            $startingTime = new \Datetime($track->time);

            if (intval($startingTime->format('H')) > 20 && intval((new \Datetime())->format('H')) < 2) {
                // We have hit a track that was played yesterday, and not today
                // Post 20:00, if we're the day after, suppose that it was yesterday
                $startingTime->sub(new \DateInterval('P1D'));
            }

            if ($tracksRepository->findOneByStartedAt($startingTime)) {
                continue;
            }

            // Create a track object holding the data
            $t = new Track();
            $t->setTitle(trim($track->titre));
            $t->setAlbum(trim($track->album ?? ''));
            $t->setArtist(trim($track->artist));
            $t->setImage(trim($track->imgSrc ?? ''));
            $t->setStartedAt($startingTime);

            // Puts a valid flag on it, depending if it's a song
            // or something else like a podcast ...
            $this->checkValid($t);

            if ($t->isValid()) {
                // Fetch from tuneefy API
                $result = $this->getTuneefyLinkAndImage($t);
                if ($result) {
                    $t->setTuneefyLink($result['link']);
                    $t->setSpotifyLink($result['spotifyLink']);
                    $t->setDeezerLink($result['deezerLink']);
                    if ($result['image']) {
                        $t->setImage($result['image']);
                    }
                }
            }

            $t->clean();

            $this->em->persist($t);
        }

        // Flush the whole thing
        $this->em->flush();

        return self::RETURN_SUCCESS;
    }

    public function getTuneefyLinkAndImage(Track $track)
    {
        $searchTerm = $track->getTitle().' '.$track->getArtist();

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: bearer '.$this->getParameter('tuneefy_token'),
            ],
            CURLOPT_URL => str_replace('%s', urlencode($searchTerm), $this->getParameter('tuneefy_track_endpoint')),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, false);
        if (!isset($data->results)) {
            return null;
        }

        $intent = $data->results[0]->share->intent;
        $image = $data->results[0]->musical_entity->album->picture;

        if (isset($data->results[0]->musical_entity->links->spotify)) {
            $spotifyLink = $data->results[0]->musical_entity->links->spotify[0];
            $spotifyLink = str_replace('https://open.spotify.com/track/', 'spotify:track:', $spotifyLink);
        } else {
            $spotifyLink = null;
        }

        if (isset($data->results[0]->musical_entity->links->deezer)) {
            $deezerLink = $data->results[0]->musical_entity->links->deezer[0];
            $deezerLink = str_replace('https://www.deezer.com/track/', '', $deezerLink);
        } else {
            $deezerLink = null;
        }

        // Get the link
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: bearer '.$this->getParameter('tuneefy_token'),
            ],
            CURLOPT_URL => str_replace('%s', $intent, $this->getParameter('tuneefy_share_endpoint')),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response);

        if (!$data) {
            return null;
        }

        return [
            'link' => $data->link,
            'spotifyLink' => $spotifyLink,
            'deezerLink' => $deezerLink,
            'image' => $image,
        ];
    }

    public function getSpotifyLinkForTuneefyLink(string $tuneefyLink): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $tuneefyLink.'?format=json',
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, false);

        if (!isset($data->links->spotify)) {
            return null;
        }

        return str_replace('https://open.spotify.com/track/', 'spotify:track:', $data->links->spotify[0]);
    }

    public function getDeezerLinkForTuneefyLink(string $tuneefyLink): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $tuneefyLink.'?format=json',
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, false);

        if (!isset($data->links->deezer)) {
            return null;
        }

        return str_replace('https://www.deezer.com/track/', '', $data->links->deezer[0]);
    }

    /**
     * Get parameter from ParameterBag.
     */
    private function getParameter(string $name)
    {
        return $this->parameterBag->get($name);
    }

    /**
     * Runs different tests to see if this Track is really a song.
     */
    private function checkValid(Track $track): void
    {
        $title = strtolower($track->getTitle());
        $album = strtolower($track->getAlbum());
        $artist = strtolower($track->getArtist());

        // Is it a RadioMeuh Jingle ?
        // ex : PetiteRadiomeuh (Jingle), Jingle, ...
        if (false !== strpos($title, 'jingle') ||
            false !== strpos($artist, 'jingle') ||
            false !== strpos($title, 'radiomeuh') ||
            false !== strpos($artist, 'radiomeuh')) {
            $track->setValid(false);

            return;
        }

        // Have we got at least 2 pieces of info out of 3 ?
        if (('' === $title && '' === $artist) ||
            ('' === $title && '' === $album) ||
            ('' === $album && '' === $artist)) {
            $track->setValid(false);

            return;
        }

        // Is a podcast ?
        if (false !== strpos($title, 'podcast')) {
            $track->setValid(false);

            return;
        }

        // Is a podcast, you sure ? It might have an url instead of an album
        if (false !== strpos($album, '.com/')) {
            $track->setValid(false);

            return;
        }

        // General exclude rules
        // ex : Moon Tapes, La Dominicale n15, Free Your Mind n18 ...
        foreach ($this->getParameter('excludes')['title'] as $regex) {
            if (preg_match($regex, $title)) {
                $track->setValid(false);

                return;
            }
        }

        foreach ($this->getParameter('excludes')['album'] as $regex) {
            if (preg_match($regex, $album)) {
                $track->setValid(false);

                return;
            }
        }

        foreach ($this->getParameter('excludes')['artist'] as $regex) {
            if (preg_match($regex, $artist)) {
                $track->setValid(false);

                return;
            }
        }

        // Is an episode of a podcast nevertheless ?
        if (preg_match("/.*S[0-9]+\s?[\-\—]\s?Ep[0-9]+.*/", $title) || preg_match("/.*Episode\s[0-9]+.*/", $title)) {
            $track->setValid(false);

            return;
        }

        $track->setValid(true);
    }
}
