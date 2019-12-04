<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Client\Client as AuroraClient;

class AuroraController extends AbstractController
{
    /**
     * @Route("/sindla/aurora", name="aurora_index", methods={"GET","HEAD"})
     */
    public function indexAction(Request $Request)
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        return $Response;
    }

    /**
     * @Route("/sindla/aurora/ip", name="aurora_ip", methods={"GET","HEAD"})
     */
    public function service(Request $Request)
    {
        /** @var AuroraClient $AuroraClient */
        $AuroraClient = $this->get('aurora.client');

        $Response = new Response($AuroraClient->ip($Request), Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        // $countryCode = $this->AuroraClient->ip2CountryCode($this->AuroraClient->ip($Request));
        return $Response;
    }
}