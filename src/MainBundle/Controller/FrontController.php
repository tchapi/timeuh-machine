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
        setlocale(LC_TIME, \Locale::getDefault(), 'fr','fr_FR','fr_FR@euro','fr_FR.utf8','fr-FR','fra');
        if ($year) {

            if ($month) {

                if ($day) {

                    // Get tracks for current year
                    $tracks = $this->get('doctrine')->getRepository(Track::class)->findByDay($year, $month, $day);

                    return $this->render('archives.day.html.twig', [
                        'tracks' => $tracks,
                        'year' => $year,
                        'month' => $month,
                        'monthName' => strftime("%B", mktime(0, 0, 0, $month)),
                        'day' => $day,
                    ]);

                }

                // Get tracks for current year
                $tracks = $this->get('doctrine')->getRepository(Track::class)->findByMonth($year, $month);

                $days = [];
                foreach ($tracks as $track) {
                    $day = $track->getStartedAt()->format('d');
                    if (isset($days[$day])){
                        if (count($days[$day]["tracks"]) < 16){
                            $days[$day]["tracks"][] = $track;
                        }
                    } else {
                        $days[$day] = [
                            "name" => $day,
                            "key" => $track->getStartedAt()->format('d'),
                            "tracks" => [$track]
                        ];
                    }
                }

                return $this->render('archives.month.html.twig', [
                    'days' => $days,
                    'year' => $year,
                    'month' => $month,
                    'monthName' => strftime("%B", mktime(0, 0, 0, $month))
                ]);
            }

             // Get tracks for current year
            $tracks = $this->get('doctrine')->getRepository(Track::class)->findByYear($year);

            $months = [];
            foreach ($tracks as $track) {
                $month = strftime("%B", mktime(0, 0, 0, $track->getStartedAt()->format('m')));
                if (isset($months[$month])){
                    if (count($months[$month]["tracks"]) < 16){
                        $months[$month]["tracks"][] = $track;
                    }
                } else {
                    $months[$month] = [
                        "name" => $month,
                        "key" => $track->getStartedAt()->format('m'),
                        "tracks" => [$track]
                    ];
                }
            }

            return $this->render('archives.year.html.twig', [
                'months' => $months,
                'year' => $year,
            ]);

        }

        return $this->redirectToRoute('archives', ["year" => date("Y")]);
        
    }
}
