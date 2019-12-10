<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Client\Client as AuroraClient;

class TestController extends AbstractController
{
    /**
     * See Resources/config/routes/routes.yaml
     */
    public function test(Request $Request)
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        return $Response;
    }

    /**
     * See Resources/config/routes/routes.yaml
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