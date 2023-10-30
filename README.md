| Version | Unit testing                                                                                                                                                     | Latest Version                                                                                                                              | Latest Version                                                                                                                 |
|---------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| 5.2     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=5.2)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.2)         | [![Latest Version](https://img.shields.io/badge/tag-v5.2.51-blue)](https://github.com/SindlaXYZ/Aurora/tree/5.2)                            | >=7.4                                                                                                                          |
| 5.3     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/workflows/PHPUnit/badge.svg?branch=5.3)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.3)         | [![Latest Version](https://img.shields.io/github/tag/SindlaXYZ/Aurora.svg)](https://github.com/SindlaXYZ/Aurora/releases)                   | >=7.4 & >= 8.0                                                                                                                 |
| 5.4     | [![PHPUnit](https://github.com/SindlaXYZ/Aurora/actions/workflows/php.yml/badge.svg?branch=5.4)](https://github.com/SindlaXYZ/Aurora/actions?query=branch%3A5.4) | [![Latest Version](https://img.shields.io/badge/tag-v5.4.1-brightgreen)](https://github.com/SindlaXYZ/Aurora/releases?q=v5.4&expanded=true) | >= 8.0         |

# Install

#### Composer
`composer require sindla/aurora:5.4.*`


#### `config/packages/aurora.yaml`:

```yaml
parameters:
    aurora.bundle:     'App'
    aurora.root:       '%kernel.project_dir%'
    aurora.tmp:        '%kernel.project_dir%/vendor/sindla/aurora/var/tmp'
    aurora.resources:  '%kernel.project_dir%/vendor/sindla/aurora/var/resources'
    aurora.static:     '%kernel.project_dir%/public/static'
    aurora.locales:    ['en', 'ro']
    aurora.locale:     'ro'
    # maxmind.com license key
    aurora.maxmind.license_key: '%env(default::MAXMIND_LICENSE_KEY)%'
    # Minify output
    aurora.minify.output:                     false
    aurora.minify.output.ignore.extensions:   ['.pdf', '.jpg', '.png', '.gif', '.doc']
    aurora.minify.output.ignore.content.type: ['text/plain']
    # https://developers.google.com/web/fundamentals/web-app-manifest
    #aurora.pwa.version_append:        "!php/eval `date('Y-m-d H')`"
    aurora.pwa.version_append:        "!php/eval `App\Utils::pwaVersioAppend()`"
    aurora.pwa.automatically_prompt:  false
    aurora.pwa.app_name:              ''
    aurora.pwa.app_short_name:        ''
    aurora.pwa.app_description:       ''
    aurora.pwa.start_url:             '/?pwa'
    aurora.pwa.display:               'fullscreen'   # fullscreen | standalone | minimal-ui
    aurora.pwa.icons:                 '%kernel.project_dir%/public/static/img/favicon'
    aurora.pwa.theme_color:           '#2C3E50' # Sets the color of the tool bar, and may be reflected in the app's preview in task switchers
    aurora.pwa.background_color:      '#2C3E50' # Should be the same color as the load page, to provide a smooth transition from the splash screen to your app
    aurora.pwa.offline:               '/aurora/pwa-offline'
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

#### `config/packages/dev/aurora.yaml`:

```yaml
parameters:
    aurora.minify.output: false
```


* Edit `composer.json` and add
```json
    "post-install-cmd": [
        "Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postInstall"
    ],
    "post-update-cmd": [
        "Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postUpdate"
    ]
```

* Edit `config/packages/twig.yaml` and add
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

* Enable Aurora Black Hole, Favicons, Manifest & PWA (Progressive Web Application) controllers
* Edit `config/routes.yaml` and prepend
```yaml
aurora:
    resource: "@AuroraBundle/Resources/config/routes/routes.yaml"
```

Run `composer update` to update and install the rest of the dependencies.

---

**[PWA]** Inside your twig template, in HTML `head` tag add:
```twig
{{ aurora.pwa(app.request) }}
```

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
        arguments: ['@Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator.inner', '@service_container']

    ...
```

2. `config/packages/doctrine_migrations.yaml`
```yaml
doctrine_migrations:
    services:
        'Doctrine\Migrations\Version\MigrationFactory': 'Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator'
    ...
```

3. Create an empty migration and edit it
```php
// Symfony
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Version... extends AbstractMigration implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        /** @var ContainerAwareInterface container */
        $this->container = $container;

        /** @var EntityManager $em */
        $em = $this->container->get('doctrine.orm.entity_manager');
    }
}
```

---

#### How to enable HTML Minifier??

* Edit `config/packages/aurora.yaml` and change `aurora.minify.output` to `true`
* Edit `config/services.yaml`
```yaml
    Sindla\Bundle\AuroraBundle\EventListener\OutputSubscriber:
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

---

#### How to access a service from a controller (DI)?

**[1/2]** Example for src/Controller/StaticController.php

```php
use Sindla\Bundle\AuroraBundle\Utils\Client\Client as AuroraClient;

class StaticController {
    private $AuroraClient;

    public function __construct(AuroraClient $AuroraClient)
    {
        /** @var AuroraClient */
        $this->AuroraClient = $AuroraClient;
    }

    public function () ...
    {
        $this->AuroraClient->ip2CountryCode($this->AuroraClient->ip($Request));
    }
}
```

**[2/2]** config/services.yaml

```yaml
services:
    App\Controller\StaticController:
        arguments:
            $AuroraClient: '@aurora.client'
```

---

#### How to access a service from a controller (direct call)?

```php
$Strink = new Strink();
```

* For favicons, can use https://www.favicon-generator.org/
