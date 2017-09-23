<?php

namespace MainBundle\Service;

use MainBundle\Entity\Track;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * The service that calls the pseudo RadioMeuh API.
 *
 * @author Cyril Chapellier
 */
final class ApiService
{
    const RETURN_SUCCESS = 1;
    const RETURN_FAILURE = 0;
    const RETURN_BAD_RESPONSE = -1;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct($entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Set ParameterBag.
     *
     * @param ParameterBagInterface $params
     */
    public function setParameterBag(ParameterBagInterface $params): void
    {
        $this->parameterBag = $params;
    }

    /**
     * Get parameter from ParameterBag.
     *
     * @param string $name
     *
     * @return mixed
     */
    private function getParameter(string $name)
    {
        return $this->parameterBag->get($name);
    }

    /**
     * Runs different tests to see if this Track is really a song.
     *
     * @param Track $track
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
            false !== strpos($title, 'radiomeuh')) {
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
        if (preg_match("/.*S[0-9]+\s?[\-\—]\s?Ep[0-9]+.*/", $title)) {
            $track->setValid(false);

            return;
        }

        $track->setValid(true);
    }

    /**
     * Retrieves the two current and last tracks from the API
     * and stores them.
     *
     * @return int
     */
    public function getCurrentAndLastTrack(): int
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [],
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_CONNECTTIMEOUT_MS => 2000,
            CURLOPT_URL => $this->getParameter('track_endpoint'),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return self::RETURN_FAILURE;
        }

        // Structure content
        try {
            $tracks = new \SimpleXMLElement($response, LIBXML_NOERROR);
        } catch (Exception $e) {
            return self::RETURN_BAD_RESPONSE;
        }

        $tracksRepository = $this->em->getRepository(Track::class);

        foreach ($tracks->track as $track) {
            // The starting time of the song is a unique identifier so we can rely
            // on that to see if we've hit the same playlist or not in this call.
            // BUT we have to be cautious since only the HH:mm:ss is indicated in
            // the API result.
            $startingTime = new \Datetime($track->time);

            if (intval($startingTime->format('H')) > 20 && 0 == intval((new \Datetime())->format('H'))) {
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
            $t->setAlbum(trim($track->album));
            $t->setArtist(trim($track->artist));
            $t->setImage(trim($track->imgSrc));
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

        return [
            'link' => $data->link,
            'spotifyLink' => $spotifyLink,
            'image' => $image,
        ];
    }

    public function getSpotifyLinkForTuneefyLink(string $tuneefyLink): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $tuneefyLink."?format=json",
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
}
