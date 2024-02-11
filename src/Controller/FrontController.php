<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Track;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Main frontend controller for the website.
 *
 * @author Cyril Chapellier
 */
final class FrontController extends AbstractController
{
    #[Route('/{page}', name: 'home', requirements: ['page' => '\d+'], defaults: ['page' => 1])]
    public function homeAction(EntityManagerInterface $em, Request $request, int $page)
    {
        $page = $page > 1 ? $page : 1;

        $trackRepository = $em->getRepository(Track::class);

        $current = $trackRepository->findCurrentlyPlayingTrack();
        $lastTracks = $trackRepository->findNLastTracksExceptCurrentOnPage($this->getParameter('tracks_per_page'), $current, $page);

        if ($request->isXmlHttpRequest()) {
            return $this->render('home.items.html.twig', [
                'lastTracks' => $lastTracks,
            ]);
        }

        return $this->render('home.html.twig', [
            'current' => $current,
            'lastTracks' => $lastTracks,
        ]);
    }

    #[Route('/about', name: 'about')]
    public function aboutAction()
    {
        return $this->render('about.html.twig');
    }

    #[Route('/archives/{year}/{month}/{day}', name: 'archives', requirements: ['year' => '\d+', 'month' => '\d+', 'day' => '\d+'])]
    public function archivesAction(EntityManagerInterface $em, Request $request, ?int $year = null, ?int $month = null, ?int $day = null)
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR', // Could be $request->getLocale()
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            'Europe/Paris',
            \IntlDateFormatter::TRADITIONAL
        );
        $formatter->setPattern('MMMM');

        if ($year) {
            if ($month) {
                if ($day) {
                    // Get tracks for current year
                    $tracks = $em->getRepository(Track::class)->findByDay($year, $month, $day);

                    return $this->render('archives.day.html.twig', [
                        'tracks' => $tracks,
                        'year' => $year,
                        'month' => $month,
                        'monthName' => $formatter->format(mktime(0, 0, 0, $month)),
                        'day' => $day,
                    ]);
                }

                // Get tracks for current month
                $tracks = $em->getRepository(Track::class)->findHighlightsByDays($year, $month);

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
                    'monthName' => $formatter->format(mktime(0, 0, 0, $month)),
                ]);
            }

            // Get tracks for current year
            $tracks = $em->getRepository(Track::class)->findHighlightsByMonths($year);

            $months = [];
            foreach ($tracks as $track) {
                $month = $track['month_n'];
                if (isset($months[$month])) {
                    $months[$month]['tracks'][] = $track[0];
                } else {
                    $months[$month] = [
                        'name' => $formatter->format(mktime(0, 0, 0, intval($track['month_n']))),
                        'key' => $track['month_n'],
                        'tracks' => [$track[0]],
                    ];
                }
            }

            return $this->render('archives.year.html.twig', [
                'months' => $months,
                'year' => $year,
            ]);
        }

        // Get all tracks for all years passed
        $tracks = $em->getRepository(Track::class)->findHighlightsByYears();

        // Sort tracks by YEAR properly
        $years = [];
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
