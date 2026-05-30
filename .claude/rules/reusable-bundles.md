# Creating Reusable Symfony Bundles

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-30 | 2026-05-30 |

**Sources:**
* https://symfony.com/doc/8.1/bundles.html
* https://symfony.com/doc/8.1/bundles/best_practices.html
* https://symfony.com/doc/8.1/bundles/configuration.html
* https://symfony.com/doc/8.1/bundles/prepend_extension.html
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/DependencyInjection/Kernel/AbstractBundle.php
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

How to author a **reusable** Symfony 8.1 bundle - a distributable package of code, config, and services shared across multiple applications (internal team libraries or open-source packages). This is the GENERAL "how to build a shareable bundle" guide. For the SPECIFIC third-party bundles the stub consumes, see the per-bundle rules `damienharper-auditor-bundle.md` and `gesdinet-jwt-refresh-token-bundle.md` - those document how to USE a particular bundle, not how to BUILD one.

## 1. What This Is - and When to Create a Reusable Bundle

A bundle packages PHP classes, configuration, services, templates, and assets so they can be installed into any Symfony application via Composer.

The key distinction, stated by the 8.1 docs (`bundles.html`):

> "In Symfony versions prior to 4.0, it was recommended to organize your own application code using bundles. This is no longer recommended and bundles should only be used to share code and features between multiple applications."

Consequences for deciding whether you need a bundle at all:

- **Application code does NOT need a bundle.** The application IS the code - controllers, entities, services, and config live directly under `src/` and `config/` and are wired by the app's own `services.yaml` (autowiring + autoconfiguration). Do not wrap application features in a bundle.
- **A reusable bundle is the case for authoring a `Bundle` / `AbstractBundle` class.** You build one only when the same code/feature must be shipped to more than one application (a private internal package reused across the team's projects, or a public open-source package). Extracting shared code into such a bundle is the one scenario where writing a bundle class is the right move.

### 1.1 Dockraft stub context

The Dockraft v8.1 stub is an **API Platform application**, NOT itself a reusable bundle - so this rule does NOT apply to day-to-day work inside the stub's `src/`. It applies when you EXTRACT shared code out of the application into a private/internal bundle reused across the team's projects.

The stack already CONSUMES exactly such a reusable bundle: `Sindla\Bundle\AuroraBundle` (installed by `symfony_install_skeleton()` in `.docker/container/scripts/symfony.sh`). That bundle is the live example of the pattern documented here - a shared bundle that several applications install rather than copy-pasting the code into each.

When you do build one, the bundle can declare its prerequisite bundles with the 8.1 `#[RequiredBundle]` attribute - see `http-less-applications.md` §4 for that attribute (FQCN, repeatable, `ignoreOnInvalid`). The DI patterns a bundle's extension uses (service definitions, tags, decoration) are in `dependency-injection.md`; this rule references them rather than repeating them.

## 2. Best Practices

These come from `bundles/best_practices.html` (the 8.1 version). They are the conventions any reusable bundle must follow; reviewers should treat deviations in a new bundle as defects.

### 2.1 Naming - namespace, bundle class, alias

- **Namespace:** PSR-4, starting with a vendor segment, then optional category segments, ending with a short name that MUST end in `Bundle`.
- **Bundle class name:** StudlyCaps, alphanumeric + underscore only, prefixed with the vendor (and optional category) and suffixed with `Bundle`. Keep the descriptive part to no more than two words.
- **Bundle alias:** the lower-cased, underscore-separated short form. It prefixes every service id, parameter, route name, and config key the bundle owns.

| Namespace | Bundle class | Alias |
|---|---|---|
| `Acme\Bundle\BlogBundle` | `AcmeBlogBundle` | `acme_blog` |
| `Acme\BlogBundle` | `AcmeBlogBundle` | `acme_blog` |

`getName()` returns the bundle class name. On `AbstractBundle` it is `final` - do not override it.

### 2.2 Directory structure (modern `src/`-based layout)

```
<your-bundle>/
├── assets/                  # web asset SOURCES (.scss, .ts, Stimulus controllers)
├── config/                  # routes, services, validation/, serialization/ config
├── docs/
│   └── index.md             # mandatory documentation entry point
├── public/                  # compiled/public web assets (CSS, JS, images)
├── src/
│   ├── Command/             # console commands
│   ├── Controller/          # controllers
│   ├── DependencyInjection/ # Extension + Configuration (TRADITIONAL approach only)
│   ├── Entity/              # Doctrine ORM entities  (Document/ for ODM)
│   ├── EventListener/       # event listeners/subscribers
│   └── AcmeBlogBundle.php   # the bundle class
├── templates/               # Twig templates
├── tests/                   # unit + functional tests
├── translations/            # XLIFF translation files
├── LICENSE
└── README.md
```

| Content | Location |
|---|---|
| Commands | `src/Command/` |
| Controllers | `src/Controller/` |
| Container extension / configuration (traditional) | `src/DependencyInjection/` |
| Doctrine ORM entities / ODM documents | `src/Entity/` / `src/Document/` |
| Event listeners | `src/EventListener/` |
| Routes, services, config | `config/` |
| Validation / serialization (when not using attributes) | `config/validation/` / `config/serialization/` |
| Public web assets (compiled) | `public/` |
| Web asset sources | `assets/` |
| Templates | `templates/` |
| Translation files | `translations/` |
| Tests | `tests/` |

### 2.3 The `getPath()` override

This `src/`-based layout is the default ONLY when the bundle class extends the recommended `AbstractBundle` - `AbstractBundle::getPath()` already returns the bundle root (the directory above `src/`).

If the bundle extends the older `Bundle` base class instead, you MUST override `getPath()` so Symfony resolves resources against the bundle root rather than `src/`:

```php
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AcmeBlogBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
```

Prefer extending `AbstractBundle` (§3) so you never write this override.

### 2.4 `composer.json`

```json
{
    "name": "acme/blog-bundle",
    "description": "Brief explanation of the bundle purpose.",
    "type": "symfony-bundle",
    "license": "MIT",
    "autoload": {
        "psr-4": { "Acme\\BlogBundle\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Acme\\BlogBundle\\Tests\\": "tests/" }
    }
}
```

- **`"type": "symfony-bundle"`** is required - it lets Symfony Flex auto-register the bundle in the consuming app's `config/bundles.php`.
- The Composer short name drops the vendor and hyphenates the words: `AcmeBlogBundle` -> `acme/blog-bundle`, `AcmeSocialConnectBundle` -> `acme/social-connect-bundle`.
- PSR-4 autoload maps the namespace to `src/`; the dev autoload maps the `Tests\` sub-namespace to `tests/`.

### 2.5 Mandatory files

- **`src/<Vendor><Name>Bundle.php`** - the bundle class (with `getPath()` only if it extends `Bundle`).
- **`README.md`** - short description, basic examples, install instructions (a single `composer require` for Flex apps; a Download + "enable in `config/bundles.php`" pair for non-Flex apps), and a link to the full docs.
- **`LICENSE`** - the full license text (most third-party bundles use MIT; any valid license is allowed).
- **`docs/index.md`** - mandatory documentation entry point. The Symfony site renders ReStructuredText (`.rst`); `.md` is accepted for in-repo docs.
- Follow **Semantic Versioning** for releases. Keep a `CHANGELOG` so consumers can see what changed between versions.

### 2.6 Service and class conventions (the inverse of an application)

A reusable bundle does NOT wire services the way an application does. The 8.1 best-practices are explicit and deliberate:

1. **Define services explicitly; do NOT rely on autowiring / autoconfiguration.** Autowiring and autoconfiguration add compile-time overhead to EVERY consuming app, so a bundle declares each service with its arguments spelled out. This is the opposite of the application-side default (where the stub's own `services.yaml` autowires `src/`).
2. **Prefix every service id with the bundle alias, not the FQCN.** A bundle's services are named `acme_blog.<something>`, not `Acme\BlogBundle\Service\Foo`. This avoids collisions and keeps the bundle's services distinct from the app's autowired class-named services.
3. **Make services private by default.** Services not meant to be fetched directly by application code are private. For the few services the app SHOULD type-hint, create a public alias from the interface/class to the service id (e.g. MonologBundle aliases `Psr\Log\LoggerInterface` to `logger`, enabling `LoggerInterface` autowiring in consuming apps).
4. **Hide internal services from `debug:container`** by prefixing the id with a dot: `.acme_blog.internal_helper`.
5. **Prefix parameters and routes with the alias too:** parameters like `acme_blog.author.email`, route names like `acme_blog_*`.

### 2.7 What goes in the bundle vs the application

- **Bundle provides:** configurable services with sane defaults, semantic configuration (§4), the classes/features being shared, and full PHPDoc on every class and function.
- **Application provides:** the decision to enable the bundle (`config/bundles.php`), the configuration VALUES, and any overrides.
- **A bundle MUST NOT:** embed third-party PHP/JS/CSS libraries (depend on them via Composer / asset tooling instead), provide a main layout (unless it ships a full working app), or override messages/templates owned by another bundle.

### 2.8 Documentation and tests

- All classes and functions ship with full PHPDoc; extended docs live under `docs/`.
- The test suite runs with a plain `phpunit` from a sample app, relies on a `phpunit.dist.xml`, lives under `tests/`, and should cover the code base well (the docs target ~95%). Functional tests assert only on response output / profiling, not internals.
- Test across the PHP and Symfony versions the bundle claims to support (a CI matrix with `--prefer-lowest` on the lowest supported Symfony). Run with `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` so the bundle never uses deprecated APIs directly.

## 3. Configuration - the Modern `AbstractBundle` Approach (8.1-preferred)

Prefer this for any new bundle. The bundle class itself defines the configuration tree and loads services - no separate `Extension` or `Configuration` class is needed.

> **8.1 namespace note.** On Symfony 8.1 the bundle/kernel infrastructure was split out of `HttpKernel` into `DependencyInjection` (the same split documented in `http-less-applications.md` §1 and §6). The real base class now lives at `Symfony\Component\DependencyInjection\Kernel\AbstractBundle` (it `implements BundleInterface, ConfigurableExtensionInterface`), and `Symfony\Component\HttpKernel\Bundle\AbstractBundle` is the backward-compatible alias the docs still import. Importing the documented `Symfony\Component\HttpKernel\Bundle\AbstractBundle` works on 8.1 and stays portable; both resolve to the same behavior.

Two methods carry the work:

- `configure(DefinitionConfigurator $definition): void` - declares the config tree.
- `loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void` - loads services using the already-merged-and-processed `$config`.

```php
// src/AcmeSocialBundle.php
namespace Acme\SocialBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AcmeSocialBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('twitter')
                    ->children()
                        ->integerNode('client_id')->end()
                        ->scalarNode('client_secret')->isRequired()->end()
                    ->end()
                ->end() // twitter
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // $config is already merged and processed against the tree from configure().
        $container->import('../config/services.php');

        $container->services()
            ->get('acme_social.twitter_client')
            ->arg(0, $config['twitter']['client_id'])
            ->arg(1, $config['twitter']['client_secret'])
        ;
    }
}
```

Exact namespaces (import these):

| Type | FQCN |
|---|---|
| `DefinitionConfigurator` | `Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator` |
| `ContainerConfigurator` | `Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator` |
| `ContainerBuilder` | `Symfony\Component\DependencyInjection\ContainerBuilder` |

### 3.1 The config tree in `configure()`

`$definition->rootNode()` returns the root of the tree; build it with the standard node builders:

- `->children()` opens the child node list; each child is closed with `->end()`.
- Node types: `->scalarNode('name')`, `->arrayNode('name')`, `->booleanNode('name')`, `->integerNode('name')`, `->floatNode('name')`, `->enumNode('name')`.
- Per-node modifiers: `->defaultValue(...)` (and `->defaultTrue()` / `->defaultFalse()` for booleans), `->isRequired()`, `->cannotBeEmpty()`, validation via `->validate()->ifTrue(...)->thenInvalid('...')`.

Split a large tree into its own file with `import()` - the config-definition analogue of importing services:

```php
public function configure(DefinitionConfigurator $definition): void
{
    $definition->import('../config/definition.php');
    // glob is allowed:
    // $definition->import('../config/definition/*.php');
}
```

```php
// config/definition.php
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->scalarNode('foo')->defaultValue('bar')->end()
        ->end()
    ;
};
```

### 3.2 Loading services in `loadExtension()`

`loadExtension()` receives the processed `$config`, a `ContainerConfigurator` to register/import service definitions, and the `ContainerBuilder` for low-level operations. Import the bundle's service file(s) with `$container->import('../config/services.php')`, then override arguments from `$config` as shown above. Keep service definitions explicit and alias-prefixed (§2.6).

### 3.3 Prepending config into other bundles - `prependExtension()`

To modify the configuration the consuming app passes to ANOTHER bundle, implement `prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void`:

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FooBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Prepend config onto another bundle's extension:
        $builder->prependExtensionConfig('framework', [
            'cache' => ['prefix_seed' => 'foo/bar'],
        ]);

        // Or prepend via the configurator, named extension + prepend: true:
        $container->extension('framework', [
            'cache' => ['prefix_seed' => 'foo/bar'],
        ], prepend: true);

        // Or import a whole config file as prepended config:
        $container->import('../config/packages/cache.php');
    }
}
```

Use prepending when your bundle has an opinion about a dependency's configuration (e.g. registering a cache prefix, a Doctrine type, or a serializer mapping) that the app should get by default without writing it itself.

## 4. Configuration - the Traditional Approach (separate Extension + Configuration)

Still fully supported. Use it when the bundle predates `AbstractBundle`, or when the container-building logic is large enough to justify its own class. Two classes live under `src/DependencyInjection/`.

The extension loads services and processes config:

```php
// src/DependencyInjection/AcmeSocialExtension.php
namespace Acme\SocialBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class AcmeSocialExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/../config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('acme_social.twitter_client');
        $definition->replaceArgument(0, $config['twitter']['client_id']);
        $definition->replaceArgument(1, $config['twitter']['client_secret']);
    }
}
```

The configuration class defines the tree via a `TreeBuilder` seeded with the bundle alias:

```php
// src/DependencyInjection/Configuration.php
namespace Acme\SocialBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acme_social');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('twitter')
                    ->children()
                        ->integerNode('client_id')->end()
                        ->scalarNode('client_secret')->isRequired()->end()
                    ->end()
                ->end() // twitter
            ->end()
        ;

        return $treeBuilder;
    }
}
```

To prepend config in the traditional approach, the extension implements `PrependExtensionInterface` and overrides `prepend(ContainerBuilder $container)`:

```php
namespace Acme\HelloBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class AcmeHelloExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            // ...
        ]);
    }
}
```

The `TreeBuilder('alias')` + `getRootNode()` tree shape is identical to §3.1; the node builders (`scalarNode`/`arrayNode`/`booleanNode`/`integerNode`, `isRequired`, `defaultValue`, `validate`) are the same in both approaches.

## 5. Choosing an Approach

| Need | Use |
|---|---|
| New bundle, configuration + service loading in one place | Modern `AbstractBundle` with `configure()` + `loadExtension()` (§3) |
| Modern bundle that must tweak another bundle's config | `AbstractBundle::prependExtension()` (§3.3) |
| Bundle predates `AbstractBundle`, or container logic is large/complex | Traditional `Extension` + `Configuration` (§4) |
| No semantic config at all, just services | A bundle class + a `config/services.php` imported in `loadExtension()` (or `load()`); skip `configure()` / `Configuration` |
| Declare prerequisite bundles | `#[RequiredBundle]` - see `http-less-applications.md` §4 |

Do NOT mix the two approaches in one bundle. If the bundle class defines `configure()` / `loadExtension()`, it must NOT also ship a `DependencyInjection\Extension` + `Configuration` pair for the same config - pick one mechanism.

## 6. Rules

1. **Author a bundle only to SHARE code across applications.** Never wrap application-only code in a bundle - the app is the code (§1).
2. **Extend `AbstractBundle`, not `Bundle`.** You inherit the modern `src/`-based `getPath()` and the `configure()` / `loadExtension()` hooks, and avoid the manual `getPath()` override (§2.3, §3).
3. **Name everything from the alias.** Service ids, parameters, and routes are prefixed with the bundle alias (`acme_blog.*`, `acme_blog_*`), never the FQCN (§2.6).
4. **Define services explicitly; do NOT autowire/autoconfigure inside the bundle.** It is the inverse of the application default and avoids per-app compile overhead (§2.6).
5. **Private by default; public via an alias.** Expose only what the app must type-hint, through an interface/class -> service-id alias. Hide internals with a leading dot (§2.6).
6. **Ship `composer.json` with `"type": "symfony-bundle"`, plus `README.md`, `LICENSE`, `docs/index.*`, and a `CHANGELOG`** under SemVer (§2.4, §2.5).
7. **Process config through the tree.** Read configuration only from the merged-and-processed `$config` array (`loadExtension`) or `processConfiguration()` (traditional) - never read raw, unvalidated input.
8. **Reference, do not embed, third-party libraries.** Depend on PHP libs via Composer and JS/CSS via the consuming app's asset tooling (§2.7).

## 7. Anti-patterns

- **Creating a bundle to organize application code.** Deprecated practice since Symfony 4; the app's own `src/` + `services.yaml` is the right home (§1).
- **Relying on autowiring / autoconfiguration inside a reusable bundle.** It pushes compile-time cost onto every consuming app; define services explicitly (§2.6).
- **FQCN service ids in a bundle.** Use alias-prefixed ids so the bundle's services never collide with the app's class-named, autowired services (§2.6).
- **Making every service public.** Leaks internals into the app's container and `debug:container`; keep them private and expose a deliberate, aliased surface (§2.6).
- **Overriding `getPath()` while extending `AbstractBundle`.** Redundant - `AbstractBundle` already returns the bundle root. The override is only for the legacy `Bundle` base (§2.3).
- **Mixing `AbstractBundle::configure()` with a separate `Configuration` class for the same config.** Two competing config trees; pick one approach (§5).
- **Embedding third-party PHP/JS/CSS libraries inside the bundle.** Bloats the package and fights the consuming app's dependency management - depend on them instead (§2.7).
- **Reading raw config in `loadExtension()` / `load()` instead of the processed `$config`.** Bypasses tree validation and defaults; always go through the definition (§3, §4).

## 8. Common Errors

| Error | Cause | Fix |
|---|---|---|
| Bundle resources (config/templates) not found; Symfony looks under `src/` | Bundle extends `Bundle` without overriding `getPath()` | Extend `AbstractBundle`, OR override `getPath()` to `return \dirname(__DIR__);` (§2.3). |
| `Unrecognized option "..." under "<alias>"` at `cache:clear` | The config key is not declared in `configure()` / `Configuration` | Add the node to the tree (§3.1 / §4), or fix the key name in the app config. |
| Bundle not registered after `composer require` | `composer.json` missing `"type": "symfony-bundle"`, OR a non-Flex app | Add the type; for non-Flex apps add the bundle to `config/bundles.php` manually. |
| `The service "..." has a dependency on a non-existent service` when the app uses the bundle | Service id assumed autowiring, or wrong alias prefix | Define services explicitly with alias-prefixed ids (§2.6). |
| App cannot autowire a bundle service by interface | The bundle exposed only a private, alias-prefixed id | Add a public alias from the interface/class to the service id (§2.6). |
| `prependExtension()` / `prepend()` has no effect | Wrong extension name passed, or prepend logic in the wrong hook | Pass the target bundle's extension alias (e.g. `'framework'`); use `prependExtension()` on `AbstractBundle` (§3.3) or `PrependExtensionInterface::prepend()` traditionally (§4). |

## 9. Version Constraints

| Package | Required |
|---|---|
| `symfony/http-kernel` | `^8.1` (provides `Symfony\Component\HttpKernel\Bundle\AbstractBundle` - the documented alias - and `Bundle`) |
| `symfony/dependency-injection` | `^8.1` (provides the relocated `Kernel\AbstractBundle` base + `ConfigurableExtensionInterface`, the `Extension` / `PrependExtensionInterface` classes, and `#[RequiredBundle]` - see `http-less-applications.md`) |
| `symfony/config` | `^8.1` (provides `DefinitionConfigurator`, `TreeBuilder`, `ConfigurationInterface`) |

The bundle conventions themselves (naming, layout, `AbstractBundle`, `configure()` / `loadExtension()`, prepending) are stable Symfony features and are documented here for the 8.1 baseline the stub targets; `CHANGELOG-8.1.md` records no behavior change to the bundle-authoring API beyond the HttpKernel -> DependencyInjection relocation noted in §3.
