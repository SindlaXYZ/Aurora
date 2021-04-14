<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlackHoleController extends AbstractController
{
    /**
     * @see /src/Resources/config/routes/routes.yaml
     */
    public function blackHole(): Response
    {
        return $this->redirect('/', Response::HTTP_PERMANENTLY_REDIRECT);
    }
}