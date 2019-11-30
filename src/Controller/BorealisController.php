<?php

namespace Sindla\Bundle\BorealisBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Sindla
use Sindla\Bundle\BorealisBundle\Utils\Client\Client as BorealisClient;

class BorealisController extends AbstractController
{
    /**
     * @Route("/sindla/borealis", name="borealis_index", methods={"GET","HEAD"})
     */
    public function indexAction(Request $Request)
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        return $Response;
    }

    /**
     * @Route("/sindla/borealis/ip", name="borealis_ip", methods={"GET","HEAD"})
     */
    public function service(Request $Request)
    {
        /** @var BorealisClient $BorelisClient */
        $BorelisClient = $this->get('borealis.client');

        $Response = new Response($BorelisClient->ip($Request), Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        // $countryCode = $this->BorelisClient->ip2CountryCode($this->BorelisClient->ip($Request));
        return $Response;
    }
}