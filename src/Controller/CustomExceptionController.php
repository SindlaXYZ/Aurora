<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class CustomExceptionController extends AbstractController
{
    public function handler(Request $request, \Throwable $exception, ?DebugLoggerInterface $logger = null)
    {
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        return $this->render(
            '@Aurora/error.html.twig',
            [
                'code'       => $statusCode,
                'title'      => "[{$statusCode}] Sorry this page does not exist!",
                'paragraphs' => [
                    "Error code {$statusCode}",
                    'The page does not exists.'
                ]
            ],
            new Response('', $statusCode)
        );
    }
}
