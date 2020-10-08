<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BlackHoleController extends Controller
{
    /**
     * @Route("/js/cms/panel/panel.js")
     * @Route("/(wp|wordpress)/wp-login.php")
     * @Route("/wp-content/plugins/mm-plugin/inc/vendors/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php")
     * @Route("/wp-login.php")
     * @Route("/fckeditor/editor/filemanager/connectors/php/upload.php")
     * @Route("/xmlrpc.php")
     * @Route("/vendor/phpunit/phpunit/phpunit.xsd")
     * @Route("/wp-includes/wlwmanifest.xml")
     * @Route("(admin|administrator|root)/index.php")
     * @Route("/administrator/")
     * @Route(".env")
     * @Route("/components/")
     */
    public function blackHole()
    {
        return $this->redirect('/');
    }
}