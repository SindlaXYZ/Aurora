| Version | Unit testing                                                                                                                                                         | Latest Version                                                                                                                               | Last Commit                                                                                                                    | Required PHP   |
|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|----------------|
| 5.2     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=5.2)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.2)             | [![Latest Version](https://img.shields.io/badge/tag-v5.2.51-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v5.2&expanded=true) | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/5.2)](https://github.com/SindlaXYZ/Aurora/tree/5.2) | >=7.4          |
| 5.3     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=5.3)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.3)             | [![Latest Version](https://img.shields.io/badge/tag-v5.3.9-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v5.3&expanded=true)  | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/5.3)](https://github.com/SindlaXYZ/Aurora/tree/5.3) | >=7.4 & >= 8.0 |
| 5.4     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/actions/workflows/phpunit.yml/badge.svg?branch=5.4)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.4)     | [![Latest Version](https://img.shields.io/badge/tag-v5.4.1-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v5.4&expanded=true) | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/5.4)](https://github.com/SindlaXYZ/Aurora/tree/5.4) | >=7.4 & >= 8.0 |
| 6.1     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=6.1)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A6.1)             | [![Latest Version](https://img.shields.io/badge/tag-N/A-red)](https://github.com/SindlaXYZ/Aurora/releases?q=v6.1&expanded=true)             | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/6.1)](https://github.com/SindlaXYZ/Aurora/tree/6.1) | >= 8.1         |
| 6.2     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=6.2)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A6.2)             | [![Latest Version](https://img.shields.io/badge/tag-v6.2.0-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v6.2&expanded=true)  | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/6.2)](https://github.com/SindlaXYZ/Aurora/tree/6.2) | >= 8.2         |
| 6.3     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/actions/workflows/phpunit.yml/badge.svg?branch=6.3)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A6.3)     | [![Latest Version](https://img.shields.io/badge/tag-v6.3.0-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v6.3&expanded=true)  | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/6.3)](https://github.com/SindlaXYZ/Aurora/tree/6.3) | >= 8.2         |
| **7.0** | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/actions/workflows/phpunit.yml/badge.svg?branch=7.0)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A7.0) | [![Latest Version](https://img.shields.io/badge/tag-v7.0.0-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v7.0&expanded=true)  | [![Last Commit](https://img.shields.io/github/last-commit/SindlaXYZ/Aurora/7.0)](https://github.com/SindlaXYZ/Aurora/tree/7.0) | >= 8.3         |

## Installation

The Aurora package is Packagist ready, and Composer can be used to install it.

```bash
composer require sindla/aurora:7.0.*
```

The x-dev flag can be used to install the development version:

```bash
composer require sindla/aurora:7.0.x-dev
```

## Configuration

Even if Aurora is Packagist-ready and is a Symfony bundle, no recipe will be installed automatically.

<details>
        <summary><h4>🗂️ config/packages/aurora.yaml</h4></summary>

* Create the file `config/packages/aurora.yaml` and add the following content:

```yaml
parameters:
    aurora.bundle: 'App'
    aurora.root: '%kernel.project_dir%'
    aurora.tmp: '%kernel.project_dir%/var/tmp'
    aurora.resources: '%kernel.project_dir%/var/resources'
    aurora.static: '%kernel.project_dir%/public/static'
    aurora.locales: [ 'en', 'ro' ]
    aurora.locale: 'ro'
    # maxmind.com license key
    aurora.maxmind.license_key: '%env(default::MAXMIND_LICENSE_KEY)%'
    # Minify output
    aurora.minify.output: false
    aurora.minify.output.ignore.extensions: [ '.pdf', '.jpg', '.png', '.gif', '.doc' ]
    aurora.minify.output.ignore.content.type: [ 'text/plain' ]
    # https://developers.google.com/web/fundamentals/web-app-manifest
    #aurora.pwa.version_append:        "!php/eval `date('Y-m-d H')`"
    aurora.pwa.enabled:                 '%env(default:true:bool:AURORA_PWA_ENABLED)%'
    aurora.pwa.debug:                   '%env(default:true:bool:AURORA_PWA_DEBUG)%'
    aurora.pwa.version_append: "!php/eval `App\Utils::pwaVersioAppend()`"
    aurora.pwa.automatically_prompt: false
    aurora.pwa.app_name: ''
    aurora.pwa.app_short_name: ''
    aurora.pwa.app_description: ''
    aurora.pwa.start_url: '/?pwa'
    aurora.pwa.display: 'fullscreen'   # fullscreen | standalone | minimal-ui
    aurora.pwa.icons: '%kernel.project_dir%/public/static/img/favicon'
    aurora.pwa.theme_color: '#2C3E50' # Sets the color of the tool bar, and may be reflected in the app's preview in task switchers
    aurora.pwa.background_color: '#2C3E50' # Should be the same color as the load page, to provide a smooth transition from the splash screen to your app
    aurora.pwa.offline: '/aurora/pwa-offline'
    aurora.pwa.precache:
        - '/'
    aurora.pwa.prevent_cache_header_request_accept:
        - 'text/html'
        - 'text/html; charset=UTF-8'
        - 'application/json'
    aurora.pwa.prevent_cache:
        - '/ajax-requests'
        - '/q'
        - '/xhr'
        - '/login'
        - '/logout'
        - '/admin'
        - '.*\.mp4' # mp4 files are large, some browsers will not be able to fully cache it, meaning the video will not be displayed
        - '.*\/match-this\/.*'
    aurora.pwa.external_cache:
        - 'fonts.gstatic.com'
        - 'fonts.googleapis.com'
    aurora.dns_prefetch:
        - 'www.google.com'
        - 'fonts.googleapis.com'
        - 'fonts.gstatic.com'
        - 'googletagmanager.com'
        - 'www.googletagmanager.com'
        - 'www.google-analytics.com'
        - 'google-analytics.com'
        - 'googleads.g.doubleclick.net'
        - 'www.googletagservices.com'
        - 'adservice.google.com'
        - 'adservice.google.ro'
        - 'www.facebook.com'
        - 'gstatic.com'
        - 'www.gstatic.com'
        - 'google.com'
        - 'google.ro'
        - 'connect.facebook.net'
        - 'youtube.com'
        - 'addthis.com'
        - 'gemius.pl'
        - 'pubmatic.com'
        - 'innovid.com'
        - 'everesttech.net'
        - 'quantserve.com'
        - 'rubiconproject.com'
        - 'facebook.com'
        - 'agkn.com'
        - 'casalemedia.com'
```

</details>

<details>
        <summary><h4>🗂️ config/packages/dev/aurora.yaml</h4></summary>

* Create the file `config/packages/dev/aurora.yaml` and add the following content:

```yaml
parameters:
    aurora.minify.output: false
```

</details>


<details>
        <summary><h4>🗂️ composer.json</h4></summary>

* Edit `composer.json` and add the following content:

```json
    "post-install-cmd": [
"Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postInstall"
],
"post-update-cmd": [
"Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postUpdate"
]
```

</details>


<details>
        <summary><h4>🗂️ config/packages/twig.yaml</h4></summary>

* Edit `config/packages/twig.yaml` and add the following content:

```yaml
twig:
    default_path: '%kernel.project_dir%/templates'
    debug: '%kernel.debug%'
    strict_variables: '%kernel.debug%'
    exception_controller: ~
    paths:
        '%kernel.project_dir%/vendor/sindla/aurora/src/templates': Aurora
    globals:
        aurora: '@aurora.twig.utility'
```

</details>

<details>
        <summary><h4>🗂️ config/routes.yaml</h4></summary>

* Will enable Aurora Black Hole, Favicons, Manifest & PWA (Progressive Web Application) controllers
* Edit `config/routes.yaml` and add the following content:

```yaml
aurora:
    resource: "@AuroraBundle/Resources/config/routes/routes.yaml"
```

</details>


Then run `composer update` to update and install the rest of the dependencies.


<details>
        <summary><h4>⚙️ Progressive Web Apps</h4></summary>

* To use Progressive Web Apps (PWA), edit your Twig template and between `<head>` and `</head>` add the following content:

```twig
{{ aurora.pwa(app.request) }}
```

</details>


<details>
        <summary><h4>⚙️ HTML Minifier</h4></summary>

* To enable HTML Minifier edit `config/packages/aurora.yaml` and change `aurora.minify.output` to `true`, then edit `config/services.yaml` add the following content:

```yaml
    Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber:
        arguments:
            $container: '@service_container'
            $utilityExtension: '@aurora.twig.utility'
              #$headers:
              #text/html:
              #Strict-Transport-Security: "max-age=1536000; includeSubDomains"
            #Content-Security-Policy: "default-src 'self'"
            # ?aurora.nonce? will be replace with uniq nonce. for twig, use {{ aurora.nonce() }}
            #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: http:; object-src 'none'"
            #Content-Security-Policy: "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'"
            #Referrer-Policy: "no-referrer-when-downgrade"
        tags:
            - { name: kernel.event_listener, event: kernel.response }
```

</details>


<details>
        <summary><h4>⚙️ MaxMind GeoLite2Country & GeoLite2City</h4></summary>

* When `composer install` and/or `composer update` are used, Aurora will try to automatically download the MaxMind GeoLite2Country & GeoLite2City
* To enable this, edit your `.env.local` and add the following content (you will need a MaxMind licence key):

```.env
MAXMIND_LICENSE_KEY=_CHANGE_THIS_WITH_YOUR_PRIVATE_LICENTE_KEY_
SINDLA_AURORA_GEO_LITE2_COUNTRY=true
SINDLA_AURORA_GEO_LITE2_CITY=true
```

* Edit `config/services.yaml` and add/append the following content:

```yaml
services:
    _defaults:
        bind:
            $auroraClient: '@aurora.client'
```

* Or edit `config/services.yaml` and add the following code to inject the `@aurora.client` only where it is needed:

```yaml
services:
    App\Controller\TestController:
        arguments:
            $auroraClient: '@aurora.client'
```

* Edit your controller and add/append the following code:

```php
<?php

namespace App\Controller;

use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/test-controller')]
final class TestController extends AbstractController
{
    public function __construct(
        protected Client $auroraClient
    )
    {
    }

    #[Route(path: '/client-ip-2-country', name: 'TestController:clientIp2Country', methods: ['OPTIONS', 'GET'])]
    #[Cache(maxage: 60, smaxage: 120, public: true, mustRevalidate: true)]
    public function clientIp2Country(Request $request): JsonResponse
    {
        return new JsonResponse([
            'countryCode' => $this->auroraClient->ip2CountryCode($this->auroraClient->ip($request))
        ]);
    }
}
```

</details>

---

#### Inject ContainerAwareInterface into Doctrine Migrations

1. `config/services.yaml`

```yaml
    ...

    ###################################################################################################################
    ### Doctrine Migration ############################################################################################
    ### Inject Container into migrations; also, check doctrine_migrations.yaml > Doctrine\Migrations\Version\MigrationFactory

    Doctrine\Migrations\Version\DbalMigrationFactory: ~
    Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator:
        decorates: Doctrine\Migrations\Version\DbalMigrationFactory
        arguments: [ '@Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator.inner', '@service_container' ]

        ...
```

2. `config/packages/doctrine_migrations.yaml`

```yaml
doctrine_migrations:
    services:
        'Doctrine\Migrations\Version\MigrationFactory': 'Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator'
    ...
```

---


* For favicons, can use https://www.favicon-generator.org/
