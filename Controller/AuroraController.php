<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuroraController extends Controller
{
    /**
     * @Route("/sindla/aurora")
     */
    public function indexAction(Request $request)
    {
        $Response = new Response('It works!', Response::HTTP_OK);
        $Response->headers->set('X-Backend-Hit', true);
        return $Response;
    }
}