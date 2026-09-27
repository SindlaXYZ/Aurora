<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TestController extends AbstractController
{
    /**
     * Actions that can be dispatched, see src/Resources/config/routes/routes.yaml
     */
    private const array ACTIONS = ['test', 'service'];

    public function __invoke(Request $Request): Response
    {
        // Use the path (not the request URI, which contains the query string) and an allow-list: any method name used to be
        // callable, e.g. "/aurora/test?/redirect" reflected the raw request (Cookie header included) and "?/__invoke" recursed forever
        $action = basename($Request->getPathInfo());

        if (!in_array($action, self::ACTIONS, true)) {
            throw $this->createNotFoundException();
        }

        return $this->$action($Request);
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function test(Request $Request): Response
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');

        return $Response;
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function service(Request $Request): Response
    {
        // AbstractController::get() was removed in Symfony 6 and AuroraClient::ip() was moved to AuroraIP::ip()
        $Response = new Response(new AuroraIP()->ip($Request), Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        $Response->headers->set('X-Robots-Tag', 'noindex');

        return $Response;
    }
}
