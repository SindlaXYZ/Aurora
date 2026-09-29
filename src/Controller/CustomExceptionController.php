<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Twig\Environment;

/**
 * config/packages/framework.yaml:
 *
 * framework:
 *     error_controller: 'Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController::handler'
 */
class CustomExceptionController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function handler(Request $request, \Throwable $exception, ?DebugLoggerInterface $logger = null): Response
    {
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        return new Response(
            $this->twig->render(
                '@Aurora/error.html.twig',
                [
                    'code'       => $statusCode,
                    'title'      => "[{$statusCode}] Sorry this page does not exist!",
                    'paragraphs' => [
                        "Error code {$statusCode}",
                        'The page does not exists.'
                    ]
                ]
            ),
            $statusCode
        );
    }
}
