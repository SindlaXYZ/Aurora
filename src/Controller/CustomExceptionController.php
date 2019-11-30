<?php

namespace Sindla\Bundle\BorealisBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class CustomExceptionController extends AbstractController
{
    public function handler(Request $request, FlattenException $exception, DebugLoggerInterface $logger = null)
    {
        return $this->render('@Borealis/error.html.twig',
            [
                'code'       => $exception->getStatusCode(),
                'title'      => "[{$exception->getStatusCode()}] Sorry this page does not exist!",
                'paragraphs' => [
                    "Error code {$exception->getStatusCode()}",
                    "The page does not exists."
                ]
            ], new Response('', 404));
    }
}