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
     * @return string
     */
    private function getParameter(string $name): string
    {
        return $this->parameterBag->get($name);
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

        error_log("now is : " . (new \Datetime())->format('c'));
        error_log(" ");
        foreach ($tracks->track as $track) {
            error_log("--------------------");
            // The starting time of the song is a unique identifier so we can rely
            // on that to see if we've hit the same playlist or not in this call.
            // BUT we have to be cautious since only the HH:mm:ss is indicated in
            // the API result.
            $startingTime = new \Datetime($track->time);
            error_log("track starting time : " . $track->time . " --> " . $startingTime->format('H'));
            if (intval($startingTime->format('H')) == 23 && intval((new \Datetime())->format('H')) == 0 ) {
                error_log("Track was YESTERDAY");
                // We have hit a track that was played yesterday, and not today
                $startingTime->sub(new \DateInterval('P1D'));
            } else {
                error_log("Track is TODAY");
            }

            error_log("Final starting time is : " . $startingTime->format('c'));
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
            $t->checkValid();

            $t->clean();

            $this->em->persist($t);
            error_log("(persisted)");
        }

        error_log(" ");
        // Fetch from tuneefy.com if needed
        // @TODO

        // Flush the whole thing
        $this->em->flush();

        return self::RETURN_SUCCESS;
    }
}
