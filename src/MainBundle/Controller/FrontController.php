<?php

namespace MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use MainBundle\Entity\Track;

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
        $tracks = $this->get('doctrine')->getRepository(Track::class)->findAll();

        return $this->render('home.html.twig', [
            'tracks' => $tracks,
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
     * @Route("/timeuh-machine", name="timeuhMachine")
     */
    public function timeuhMachineAction(Request $request)
    {
        $tracks = $this->get('doctrine')->getRepository(Track::class)->findAll();

        return $this->render('timeuhMachine.html.twig', [
            'tracks' => $tracks,
        ]);
    }
}
