# Install

#### Composer
`composer require sindla/aurora:3.4.*`

#### `app/AppKernel.php`
```php
    $bundles = [
        new \Sindla\Bundle\AuroraBundle\AuroraBundle()
    ];
```

#### `app/config/config.yml`:

```yaml
aurora:
    root:       '%kernel.root_dir%/..'
    tmp:        '%kernel.root_dir%/../var/tmp'
    resources:  '%kernel.root_dir%/../var/resources/'
    locale:     '%locale%'
    bundle:     'App'

twig:
    globals:
        aurora: '@aurora.twig.utility'
```
* Edit `composer.json` and add
```json
    "post-update-cmd": [
        "Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postUpdate"
    ]
```

Run `composer update` to update and install the rest of the dependencies.

---

#### How to enable Black Hole controller ?

**[1/1]** `app/config/routing.yml`
```yml
aurora:
    resource: '@AuroraBundle/Controller/'
    type: annotation
```