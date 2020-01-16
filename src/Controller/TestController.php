<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Client\Client as AuroraClient;

class TestController extends AbstractController
{
    public function __invoke(Request $Request)
    {
        $parts = explode('/', $Request->getRequestUri());
        $action = end($parts);

        if (method_exists($this, $action)) {
            return $this->$action($Request);
        }
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function test(Request $Request)
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');
        return $Response;
    }

    /**
     * See src/Resources/config/routes/routes.yaml
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