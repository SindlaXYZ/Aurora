<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class BlackHoleController
{
    /**
     * @see /src/Resources/config/routes/routes.yaml
     */
    public function blackHole(): Response
    {
        return new RedirectResponse('/', Response::HTTP_PERMANENTLY_REDIRECT);
    }
}
