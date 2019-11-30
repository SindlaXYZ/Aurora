# Install

#### Composer
* PRD: `composer require sindla/borealis`
* DEV: `composer require sindla/borealis:dev-master`


#### `config/packages/borealis.yaml`:

```yaml
parameters:
    borealis.bundle:     'App'
    borealis.root:       '%kernel.project_dir%'
    borealis.tmp:        '%kernel.project_dir%/var/tmp'
    borealis.resources:  '%kernel.project_dir%/var/resources'
    borealis.static:     '%kernel.project_dir%/public/static'
    borealis.locales:    ['en', 'ro']
    borealis.locale:     'ro'
    # Minify output
    borealis.minify.output:                     false
    borealis.minify.output.ignore.extensions:   ['.pdf', '.jpg', '.png', '.gif', '.doc']
    borealis.minify.output.ignore.content.type: ['text/plain']
    # https://developers.google.com/web/fundamentals/web-app-manifest
    borealis.pwa.app_name:          ''
    borealis.pwa.app_short_name:    ''
    borealis.pwa.app_description:   ''
    borealis.pwa.start_url:         '/?pwa'
    borealis.pwa.display:           'fullscreen'   # fullscreen | standalone | minimal-ui
    borealis.pwa.icons:             '%kernel.project_dir%/public/static/img/favicon'
    borealis.pwa.theme_color:       '#2C3E50' # Sets the color of the tool bar, and may be reflected in the app's preview in task switchers
    borealis.pwa.background_color:  '#2C3E50' # Should be the same color as the load page, to provide a smooth transition from the splash screen to your app
    borealis.pwa.offline:           '/pwa-offline'
    borealis.pwa.precache:
        - '/'
    borealis.pwa.prevent_cache:
        - '/ajax-requests'
        - '/q'
    borealis.pwa.external_cache:
        - 'fonts.gstatic.com'
        - 'fonts.googleapis.com'
```
* Edit `composer.json` and add 
```json
    "post-update-cmd": [
        "Sindla\\Bundle\\BorealisBundle\\Composer\\ScriptHandler::postUpdate" 
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
        '%kernel.project_dir%/vendor/sindla/borealis/src/templates': Borealis
    globals:
        borealis: '@borealis.twig.utility'
```

Run `composer update` to update and install the rest of the dependencies.

---

#### How to enable Custom Error controller fro PROD ?

**[1/1]** `config/packages/prod/twig.yaml`
```yml
twig:
    exception_controller: 'Sindla\Bundle\BorealisBundle\Controller\CustomExceptionController::handler'
```


---

#### How to enable Black Hole controller ?

**[1/1]** `app/config/routing.yml`
```yml
borealis.blackhole:
    resource: '@BorealisBundle/Controller/BlackHoleController.php'
    type: annotation
```

---

#### How to enable Favicons, Manifest & PWA (Progressive Web Application) ?

**[1/2]** `app/config/routing.yml`
```yml
borealis.pwa:
    resource: '@BorealisBundle/Controller/PWAController.php'
    type: annotation
```

**[2/2]** Inside your twig template, in HTML `head` tag add: 
```twig
{{ borealis.pwa(app.request) }}
```

---

#### How to enable HTML Minifier??

* Edit `config/packages/borealis.yaml` and change `borealis.minify.output` to `true`
* Edit `templates/services/services.html.twig`
```yamp
    Sindla\Bundle\BorealisBundle\EventListener\OutputSubscriber:
        arguments: ['@service_container']
        tags:
            - { name: kernel.event_listener, event: kernel.response }
```

---

#### How to access a service from a controller (DI)?

**[1/2]** Example for src/Controller/StaticController.php

```php
use Sindla\Bundle\BorealisBundle\Utils\Client\Client as BorelisClient;

class StaticController {
    private $BorelisClient;
    
    public function __construct(BorelisClient $BorelisClient)
    {
        /** @var BorelisClient */
        $this->BorelisClient = $BorelisClient;
    }
    
    public function () ...
    {
        $this->BorelisClient->ip2CountryCode($this->BorelisClient->ip($Request));
    }
}
```

**[2/2]** config/services.yaml 

```yaml
services:
    App\Controller\StaticController:
        arguments:
            $BorelisClient: '@borealis.client'
```

---

#### How to access a service from a controller (direct call)?

```php
$Strink = new Strink();
```

* For favicons, can use https://www.favicon-generator.org/