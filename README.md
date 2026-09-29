## Aurora

[![PHPUnit](https://github.com/SindlaXYZ/Aurora/actions/workflows/phpunit.yml/badge.svg?branch=8.1)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A8.1) ![PHPUnitTests](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/phpunit.svg?raw=true) ![PHPUnitStatements](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/statements.svg?raw=true) ![PHPUnitCoverage](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/coverage.svg?raw=true) [![Forbidden Files](https://github.com/SindlaXYZ/Aurora/actions/workflows/forbidden-files.yml/badge.svg?branch=8.1)](https://github.com/SindlaXYZ/Aurora/actions/workflows/forbidden-files.yml) [![Last Commit](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/last-commit.svg?raw=true)](https://github.com/SindlaXYZ/Aurora/tree/8.1) [![Latest Version](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/tag.svg?raw=true)](https://github.com/SindlaXYZ/Aurora/releases?q=v8.1&expanded=true)

![PHPUnitCoverageTrend](https://github.com/SindlaXYZ/aurora/blob/8.1/.github/badges/coverage-trend.svg?raw=true)

## Installation

The Aurora package is Packagist ready, and Composer can be used to install it (PHP 8.4+ required).

```bash
composer require sindla/aurora:8.1.*
```

The x-dev flag can be used to install the development version:

```bash
composer require sindla/aurora:8.1.x-dev
```

## Configuration

Even though Aurora is Packagist-ready and is a Symfony bundle, no recipe will be installed automatically.

<details>
        <summary><h4>🗂️ config/packages/aurora.yaml</h4></summary>

* Create the file `config/packages/aurora.yaml` and add the following content (the reference template is
  `src/Resources/schema/packages/aurora.yaml`):

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
    aurora.minify.output.ignore.extensions:   ['.pdf', '.csv', '.jpg', '.png', '.gif', '.doc', '.docx', '.xls', '.xlsm', '.xlsx', '.xml', '.zip']
    aurora.minify.output.ignore.content.type: ['text/plain', 'text/csv', 'application/octet-stream', 'image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/xml', 'application/zip']
    # Replace strings in the response content (strtr() map, "search" => "replace")
    aurora.minify.replace: false
    aurora.minify.replace.mapper: { }
    # https://developers.google.com/web/fundamentals/web-app-manifest
    # Optional suffix appended to the PWA / service-worker version (used in the SW cache names).
    # It MUST be stable for the lifetime of a deploy (a build hash, a release tag, APP_VERSION).
    # NEVER use a time-based or per-request value (e.g. date()/time()/a session id): the service
    # worker embeds the version in its cache names, so a value that changes between requests makes
    # the browser treat the worker as updated and shows a false "new version available" prompt on
    # every revisit. Leave empty to use the git hash alone, set a stable string / env var, or
    # implement App\Service\AuroraService::pwaVersionAppend(): string (auto-detected when present).
    aurora.pwa.version_append: ''
    #aurora.pwa.version_append: '%env(default::APP_VERSION)%'
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

* Optional parameters: `aurora.pwa.enabled` (default `true`), `aurora.pwa.debug` (default `false`) and
  `aurora.pwa.prevent_cache_header_request_accept` (default `[]`, e.g. `[ 'text/html', 'application/json' ]`).

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
    globals:
        aurora: '@aurora.twig.utility'
```

* The templates of the bundle are registered by TwigBundle as the `@Aurora` namespace: a `paths` entry is not needed (the
  `exception_controller` option was removed in Symfony 5, see the error page below).

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

<details>
        <summary><h4>🗂️ config/packages/framework.yaml</h4></summary>

* Optional: to render the errors with the Aurora error page, edit `config/packages/framework.yaml` and add the following content:

```yaml
framework:
    error_controller: 'Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController::handler'
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
        # An event subscriber: a "kernel.event_listener" tag registered it a second time (with autoconfigure), so it ran twice
        tags: [kernel.event_subscriber]
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
SINDLA_AURORA_GEO_LITE2_ASN=true
```

* The public services of the bundle (`aurora.client`, `aurora.ip`, `aurora.pwa`, ...) are autowired by their class, e.g. edit your
  controller and add/append the following code:

```php
<?php

namespace App\Controller;

use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIP\AuroraIP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/test-controller')]
final class TestController extends AbstractController
{
    public function __construct(
        private readonly AuroraClient $auroraClient,
        private readonly AuroraIP $auroraIP,
    ) {
    }

    #[Route(path: '/client-ip-2-country', name: 'TestController:clientIp2Country', methods: ['OPTIONS', 'GET'])]
    #[Cache(maxage: 60, smaxage: 120, public: true, mustRevalidate: true)]
    public function clientIp2Country(Request $request): JsonResponse
    {
        return new JsonResponse([
            'countryCode' => $this->auroraClient->ip2CountryCode($this->auroraIP->ip($request))
        ]);
    }
}
```

* Or inject a service by its id, e.g. `$auroraClient: '@aurora.client'` in `config/services.yaml`.

</details>

---

#### Inject the service container into Doctrine Migrations

`Symfony\Component\DependencyInjection\ContainerAwareInterface` was removed in Symfony 7: the decorator injects the container into every
migration that declares a public `setContainer(ContainerInterface $container)` method.

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

#### Client IP behind a reverse proxy or a load balancer

`AuroraIP::ip()` (used by the Twig `ip2Country()` / `ip2County()` / `ip2City()` functions, the Monolog `MiscProcessor` and the
BlackHole API) reads the client IP with `Request::getClientIp()`: the `X-Forwarded-For` header is honoured only when the request
comes from a proxy listed in `framework.trusted_proxies`. The `CF-Connecting-IP` header is honoured only when the request comes
from a [Cloudflare edge server](https://www.cloudflare.com/ips/).

```yaml
# config/packages/framework.yaml
framework:
    trusted_proxies: 'private_ranges' # or the IPs / ranges of your proxies
    trusted_headers: [ 'x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port', 'x-forwarded-prefix' ]
```

---

* For favicons, can use https://www.favicon-generator.org/

---

#### DEV

```bash
cd installed/
cp ../composer.json composer.json
composer install

mkdir symfony/
cd symfony/
composer create-project symfony/skeleton:8.1.* . --no-cache
yes | composer require symfony/webapp-pack
yes | composer require sindla/aurora:8.1.x-dev -W --no-cache --no-progress
yes | composer require phpunit/phpunit:^12.4 -W --dev --no-progress
yes | composer require dama/doctrine-test-bundle:^8.6 -W --dev --no-progress
php vendor/bin/phpunit -c phpunit.dist.xml vendor/sindla/aurora/tests/
php vendor/bin/phpunit -c vendor/sindla/aurora/phpunit.xml.dist vendor/sindla/aurora/tests/
```
