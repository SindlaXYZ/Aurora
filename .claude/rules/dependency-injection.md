# Dependency Injection - 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-dependency-injection-improvements
* https://symfony.com/doc/8.1/service_container.html
* https://symfony.com/doc/8.1/service_container/service_decoration.html
* https://symfony.com/doc/8.1/service_container/autowiring.html
* https://symfony.com/doc/8.1/service_container/tags.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/dependency-injection/blob/8.1/Attribute/AsTagDecorator.php

Documents the Dependency Injection additions introduced in Symfony 8.1. Everything here is **additive** - existing 8.0 wiring keeps working - except the three deprecations in section 11, which still work in 8.1 but are scheduled for removal in 9.0.

The 7.3 / 7.4 DI patterns the stub already treats as preferred (service-closure shorthand `@>service_id`, environment-scoped `#[AsAlias(when:)]`, resource tags + `#[AutoconfigureResourceTag]`, repeatable `#[AsDecorator]`, `#[AutowireMethodOf]`) are documented in the **v8.0** stub's `dependency-injection.md` and remain valid on 8.1 - this file does NOT repeat them. It covers only what 8.1 adds on top.

## 1. What Changed in Symfony 8.1

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Inject an env var lazily | `#[Autowire(env: 'FOO')] string $foo` - resolved eagerly at container build | `#[Autowire(env: 'FOO')] \Closure $foo` / `string\|\Stringable $foo` - resolution deferred until first use |
| Pick one of several aliases for the same interface | `#[Target('name')]` matched a name-based autowiring alias declared elsewhere | `#[AsAlias(Interface::class, target: 'name')]` declares the alias + target together, next to the implementation |
| `Definition::setFactory()` / `setConfigurator()` argument | A service reference or a `[service, method]` array | Also accepts an inline `Definition` directly |
| A service stack decorating an existing service | Not possible - stacks could not declare `decorates` | `decorates` / `decorates_tag` now work on `stack:` definitions |
| Decorate every service carrying a tag | Custom compiler pass | Declarative `decorates_tag` config key + `#[AsTagDecorator]` attribute |
| `ContainerConfigurator::import()` | Imports the whole glob | New `exclude:` argument skips matching paths |
| Env var names | Letters / digits / underscore | May now contain `.` (e.g. `DATABASE.PRIMARY.URL`) |
| Ordering security voters | Custom priority wiring | `#[AsTaggedItem(priority:)]` is honored for voter order |
| A bundle that also runs container logic | Separate `CompilerPassInterface` class registered in `build()` | The bundle class itself can act as a compiler pass |

## 2. Lazy Env Vars via `#[Autowire(env: ...)]` (Closure / `Stringable`)

An env var injected through `#[Autowire(env: 'FOO')]` is normally resolved when the container is built. In 8.1 you can defer that resolution by typing the target as `\Closure` (call it to read the value) or `string|\Stringable` (resolved on first string coercion). This matters when the env var is expensive to compute, may be unset in some environments, or is only needed on a rarely-hit code path.

```php
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Worker
{
    public function __construct(
        #[Autowire(env: 'DB_URL')]
        private \Closure $dbUrl,

        #[Autowire(env: 'APP_NAME')]
        private string|\Stringable $appName = 'default',

        // Embedded env vars in any string also work:
        #[Autowire('redis://%env(HOST)%:%env(PORT)%')]
        private \Stringable $redisDsn,
    ) {
    }
}
```

YAML equivalent - the new `!env_closure` tag (with an optional default as the second array element):

```yaml
services:
    App\Worker:
        arguments:
            - !env_closure '%env(DB_URL)%'
            - !env_closure ['%env(APP_NAME)%', 'default']
```

Call the closure to obtain the value: `($this->dbUrl)()`. A `\Stringable` resolves when cast: `(string) $this->redisDsn`.

## 3. `target` on `#[AsAlias]` (Pair an Alias With a `#[Target]` Name)

When several classes implement the same interface, 8.1 lets the implementation declare the named alias itself via the new `target:` parameter on `#[AsAlias]`. Consumers then select it with `#[Target('name')]` - the same `#[Target]` already used for tagged services.

```php
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(StorageInterface::class, target: 'image')]
class ImageStorage implements StorageInterface
{
}

#[AsAlias(StorageInterface::class, target: 'document')]
class DocumentStorage implements StorageInterface
{
}
```

Injection side - pick the target by name; the parameter can be named anything:

```php
public function __construct(
    #[Target('image')] private StorageInterface $storage,
) {
}
```

Before 8.1 the same selection relied on the **parameter name** matching a name-based alias (`$imageStorage` resolving to the "image" alias). That implicit matching is now deprecated (section 11) - declare `target:` + `#[Target]` explicitly instead. This is the 8.1 evolution of the environment-scoped `#[AsAlias(when:)]` pattern documented in the v8.0 `dependency-injection.md`; the two parameters are independent and may be combined.

## 4. Inline `Definition` as Factory and Configurator

`Definition::setFactory()` and `Definition::setConfigurator()` now accept an inline `Definition` directly, so a one-off factory/configurator service does not need to be registered separately just to be referenced.

```php
use Symfony\Component\DependencyInjection\Definition;

$container->register('app.handler', HandlerClass::class)
    ->setFactory(new Definition(InvokableFactory::class))
    ->setConfigurator(new Definition(InvokableConfigurator::class));
```

Use this for factories/configurators that exist solely to build one service - it keeps them anonymous instead of polluting the service id space.

## 5. Service Stacks as Decorators

A [service stack](https://symfony.com/doc/8.1/service_container/service_decoration.html#stacking-decorators) composes several services into a chain where each layer wraps the next via `@.inner`. Before 8.1 a stack could not decorate an existing service - you had to wire the decoration manually. 8.1 adds `decorates` (and `decorates_tag`, section 6) to stack definitions; the innermost service in the stack becomes the decorator of the target.

```yaml
services:
    my_stack:
        decorates: api_platform.serializer.context_builder
        stack:
            - class: App\Decorator\AddGroupsContextBuilder
              arguments: ['@.inner']
            - class: App\Decorator\AddFiltersContextBuilder
              arguments: ['@.inner']
```

The existing stack options `decoration_inner_name`, `decoration_priority`, and `decoration_on_invalid` apply to a decorating stack exactly as they do to a regular decorated service.

## 6. Generic Tag Decoration - `decorates_tag` and `#[AsTagDecorator]`

Decorating every service that carries a given tag previously required a custom compiler pass. 8.1 makes it declarative, as a config key on a stack (`decorates_tag` - the stack is cloned once per tagged service, each clone decorating a different target) and as a standalone attribute on a single decorator class.

The attribute is `Symfony\Component\DependencyInjection\Attribute\AsTagDecorator`. Its constructor is `(string $tag, int $priority = 0, int $onInvalid = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)`:

```php
use Symfony\Component\DependencyInjection\Attribute\AsTagDecorator;

#[AsTagDecorator('app.handler')]
class LoggingHandler
{
    public function __construct(private object $inner)
    {
    }
}
```

YAML equivalent on a plain service definition:

```yaml
services:
    app.logging_handler:
        class: App\Decorator\LoggingHandler
        decorates_tag: app.handler
```

Every service tagged `app.handler` is now wrapped by `LoggingHandler`. Use this for cross-cutting concerns applied to a whole family of services (logging, tracing, caching, metrics).

`#[AsTagDecorator]` is for decorating an open-ended set selected by TAG. To decorate a fixed, named set of services with one decorator class, the repeatable `#[AsDecorator]` (documented in the v8.0 `dependency-injection.md`) is still the right tool - it lists each target explicitly.

## 7. `exclude` on `ContainerConfigurator::import()`

`import()` gains an `exclude:` argument that skips paths matching the given glob(s) - the DI-config analogue of the `exclude:` already available on the `_defaults` resource directive.

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $configurator->import('services/*.php', exclude: [
        'services/legacy/*',
        'services/dev_only.php',
    ]);
};
```

Use it to import a directory wholesale while carving out files that must not be loaded (legacy config, environment-specific fragments).

## 8. Environment Variable Names May Contain `.`

`%env(...)%` now accepts a dot in the variable name, so hierarchical names exported by some platforms (Kubernetes, certain secret managers) can be read directly without renaming.

```yaml
services:
    App\Repository\UserRepository:
        arguments:
            $dsn: '%env(DATABASE.PRIMARY.URL)%'
```

```dotenv
# .env or container environment
DATABASE.PRIMARY.URL="postgresql://..."
```

This is purely about the variable NAME. Env-var processors (`%env(int:...)%`, `%env(resolve:...)%`, etc.) work the same on dotted names.

## 9. `#[AsTaggedItem]` Honored for Security Voter Ordering

Security voters are tagged `security.voter`. In 8.1 the `priority:` declared via `#[AsTaggedItem]` is honored for voter order, so a voter's evaluation position is set declaratively next to the class instead of via separate tag-priority wiring.

```php
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

#[AsTaggedItem(priority: 10)]
final class PostVoter extends Voter
{
    // ...
}
```

Higher priority runs first. This matters only when voter order is significant (e.g. an `affirmative` strategy where an early voter should short-circuit, or a custom strategy that depends on order).

## 10. Bundles as Compiler Passes

A bundle class can now act as a compiler pass directly, instead of defining a separate `CompilerPassInterface` class and registering it from the bundle's `build()` method. For a bundle whose only container-time work is one small pass, this removes the extra class and the `build()` wiring boilerplate. When the pass logic is substantial or reused, a dedicated `CompilerPassInterface` class is still the clearer choice.

## 11. Deprecations Introduced in 8.1 (stop using these)

Each still works in 8.1 but is scheduled for removal in 9.0. When touching affected wiring, migrate it.

| Deprecated | Replacement |
|---|---|
| Named autowiring alias selected by **parameter name** alone (no `#[Target]`) | Declare `target:` on `#[AsAlias]` and select with `#[Target('name')]` (section 3) |
| Default index / priority methods when defining tagged locators / iterators (the `default_index_method` / `default_priority_method` options and the corresponding default-naming methods on the tagged class) | Set them per item with `#[AsTaggedItem(index: '...', priority: N)]` |
| Invalid options passed to `from_callable` | Remove the unsupported options from the `from_callable` definition |

## 12. Combined Pattern - Stub Recommendations

For new code in this stub, on top of the 7.3 / 7.4 patterns in the v8.0 `dependency-injection.md`:

| Need | Use it for |
|---|---|
| Lazy / optional / expensive env var | `#[Autowire(env: 'FOO')] \Closure` (or `string\|\Stringable`) - section 2 |
| Several impls of one interface, pick by name | `#[AsAlias(Interface::class, target: 'x')]` + `#[Target('x')]` - section 3 |
| Decorate an EXISTING service via a chain | `stack:` + `decorates:` - section 5 |
| Decorate ALL services with a tag | `#[AsTagDecorator('tag')]` (open set) - section 6 |
| Decorate a FIXED named set with one class | repeatable `#[AsDecorator]` (v8.0 file) |
| Import a dir but skip some files | `import(..., exclude: [...])` - section 7 |
| Order security voters | `#[AsTaggedItem(priority:)]` on the voter - section 9 |
| One small container pass owned by a bundle | bundle-as-compiler-pass - section 10 |

## 13. Anti-patterns

- **Typing a lazy env var as `\Closure` and forgetting to call it** - `$this->dbUrl` is the closure, not the value. Read it with `($this->dbUrl)()`. A `\Stringable` must be cast: `(string) $this->redisDsn`.
- **Keeping parameter-name-based autowiring aliases** - deprecated in 8.1. A future reader cannot tell `$imageStorage` is bound to the "image" alias by anything other than the variable name. Declare `target:` + `#[Target]` so the binding is explicit.
- **Reaching for `#[AsTagDecorator]` to decorate a single known service** - that is what `#[AsDecorator]` is for. `#[AsTagDecorator]` decorates an open-ended set by tag; using it for one service hides the actual target.
- **Using `decorates_tag` AND `#[AsDecorator]` on the same family** - pick one decoration mechanism per concern; combining them duplicates the wrapping and the order becomes hard to reason about.
- **Putting a heavyweight compiler pass into the bundle class** - bundle-as-compiler-pass is for one small pass. Substantial or reusable logic belongs in its own `CompilerPassInterface` class.
- **Relying on voter `priority` when the access-decision strategy ignores order** - `#[AsTaggedItem(priority:)]` only changes the evaluation order; with a `unanimous` strategy that consults every voter regardless, ordering rarely changes the outcome.

## 14. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `TypeError: ... must be of type Closure, string given` on a `#[Autowire(env:)]` argument | The env var resolves to a string but the parameter is typed `\Closure` without the lazy form taking effect | Confirm `symfony/dependency-injection` is `^8.1`; on older versions `env:` resolves eagerly to a string. |
| `Cannot autowire service "...": multiple services implement "...Interface"` | Two `#[AsAlias(Interface::class)]` on different classes without distinct `target:` | Give each alias a different `target:` and select with `#[Target('...')]`. |
| `Invalid alias` / wrong implementation injected after upgrade | Code still selected an alias by parameter name (now deprecated and being phased out) | Add `target:` to the `#[AsAlias]` and `#[Target]` on the consumer. |
| `Environment variable not found: "DATABASE.PRIMARY.URL"` | Env var with a dot on `symfony/dependency-injection` < 8.1 | Upgrade to `^8.1`; dotted names are rejected before then. |
| `decorates_tag` ignored / no decoration applied | `symfony/dependency-injection` < 8.1, or the target services are not actually tagged | Verify the package version and that the services carry the tag (`debug:container --tag=app.handler`). |
| Deprecation: `Defining a default index/priority method ... is deprecated` | A tagged locator/iterator uses `default_index_method` / `default_priority_method` | Move index/priority onto each service with `#[AsTaggedItem(index:, priority:)]`. |

## 15. Version Constraints

| Package | Required |
|---|---|
| `symfony/dependency-injection` | `^8.1` (lazy `#[Autowire(env:)]` closures/`Stringable` + `!env_closure`, `target:` on `#[AsAlias]`, inline `Definition` factory/configurator, service stacks as decorators, `decorates_tag` + `#[AsTagDecorator]`, `exclude:` on `import()`, dotted env-var names, voter ordering via `#[AsTaggedItem]`, bundles as compiler passes). The 7.3 / 7.4 DI features (`@>service_id`, `#[AsAlias(when:)]`, resource tags, repeatable `#[AsDecorator]`, `#[AutowireMethodOf]`) are documented in the v8.0 stub and require `^7.3` / `^7.4`. |
| `symfony/security-bundle` | `^8.1` (for `#[AsTaggedItem]`-driven voter ordering) |
