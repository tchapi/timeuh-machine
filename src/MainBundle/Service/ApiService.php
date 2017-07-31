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
        if (strpos($title, 'jingle') !== false ||
            strpos($artist, 'jingle') !== false ||
            strpos($title, 'radiomeuh') !== false) {
            $track->setValid(false);
            return;
        }

        // Have we got at least 2 pieces of info out of 3 ?
        if (($title === "" && $artist === "") ||
            ($title === "" && $album === "") ||
            ($album === "" && $artist === "")) {
            $track->setValid(false);
            return;
        }

        // Is a podcast ?
        if (strpos($title, 'podcast') !== false) {
            $track->setValid(false);
            return;
        }

        // Is a podcast, you sure ? It might have an url instead of an album
        if (strpos($album, '.com/') !== false) {
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
            
            if (intval($startingTime->format('H')) == 23 && intval((new \Datetime())->format('H')) == 0) {
                // We have hit a track that was played yesterday, and not today
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

            $t->clean();

            $this->em->persist($t);
        }

        // Fetch from tuneefy.com if needed
        // @TODO

        // Flush the whole thing
        $this->em->flush();

        return self::RETURN_SUCCESS;
    }
}
