# Install

#### Composer
`composer require sindla/aurora:5.0.*`


#### `config/packages/aurora.yaml`:

```yaml
parameters:
    aurora.bundle:     'App'
    aurora.root:       '%kernel.project_dir%'
    aurora.tmp:        '%kernel.project_dir%/var/tmp'
    aurora.resources:  '%kernel.project_dir%/var/resources'
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
    aurora.pwa.app_name:          ''
    aurora.pwa.app_short_name:    ''
    aurora.pwa.app_description:   ''
    aurora.pwa.start_url:         '/?pwa'
    aurora.pwa.display:           'fullscreen'   # fullscreen | standalone | minimal-ui
    aurora.pwa.icons:             '%kernel.project_dir%/public/static/img/favicon'
    aurora.pwa.theme_color:       '#2C3E50' # Sets the color of the tool bar, and may be reflected in the app's preview in task switchers
    aurora.pwa.background_color:  '#2C3E50' # Should be the same color as the load page, to provide a smooth transition from the splash screen to your app
    aurora.pwa.offline:           '/pwa-offline'
    aurora.pwa.precache:
        - '/'
    aurora.pwa.prevent_cache:
        - '/ajax-requests'
        - '/q'
        - '/xhr'
        - '/login'
        - '/logout'
        - '/admin'
    aurora.pwa.external_cache:
        - 'fonts.gstatic.com'
        - 'fonts.googleapis.com'
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

* Edit `config/routes.yaml` and prepend
```yaml
aurora:
    resource: "@AuroraBundle/Resources/config/routes/routes.yaml"
```

Run `composer update` to update and install the rest of the dependencies.

---

#### How to enable Black Hole controller ?

**[1/1]** `app/config/routing.yml`
```yml
aurora.blackhole:
    resource: '@AuroraBundle/Controller/BlackHoleController.php'
    type: annotation
```

---

#### How to enable Favicons, Manifest & PWA (Progressive Web Application) ?

**[1/2]** `app/config/routing.yml`
```yml
aurora.pwa:
    resource: '@AuroraBundle/Controller/PWAController.php'
    type: annotation
```

**[2/2]** Inside your twig template, in HTML `head` tag add: 
```twig
{{ aurora.pwa(app.request) }}
```

---

#### How to enable HTML Minifier??

* Edit `config/packages/aurora.yaml` and change `aurora.minify.output` to `true`
* Edit `config/services.yaml`
```yamp
    Sindla\Bundle\AuroraBundle\EventListener\OutputSubscriber:
        arguments: ['@service_container']
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