<?php

namespace MainBundle\Controller;

use MainBundle\Entity\Track;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main frontend controller for the website.
 *
 * @author Cyril Chapellier
 */
class FrontController extends Controller
{
    /**
     * @Route("/", name="home")
     */
    public function homeAction(Request $request)
    {
        $trackRepository = $this->get('doctrine')->getRepository(Track::class);

        $current = $trackRepository->findCurrentlyPlayingTrack();
        $lastTracks = $trackRepository->findNLastTracksExceptCurrent($this->getParameter('tracks_per_page'), $current);

        return $this->render('home.html.twig', [
            'current' => $current,
            'lastTracks' => $lastTracks,
        ]);
    }

    /**
     * @Route("/about", name="about")
     */
    public function aboutAction(Request $request)
    {
        return $this->render('about.html.twig');
    }

    /**
     * @Route("/archives/{year}/{month}/{day}", name="archives", requirements={"year" = "\d+", "month" = "\d+", "day" = "\d+"})
     */
    public function archivesAction(Request $request, ?int $year = null, ?int $month = null, ?int $day = null)
    {
        // Get tracks for current month
        $tracks = $this->get('doctrine')->getRepository(Track::class)->findAll();

        // Infinite scroll to get the other months

        return $this->render('archives.html.twig', [
            'tracks' => $tracks,
        ]);
    }
}
