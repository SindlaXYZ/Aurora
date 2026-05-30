# Symfony Routing - 7.3 / 7.4 Baseline plus 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-curated-new-features
* https://symfony.com/doc/8.1/routing.html
* https://symfony.com/doc/8.1/controller.html#mapping-the-whole-query-string
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

Covers the routing features the stub uses or recommends. The 7.3 / 7.4 capabilities (sections 1-7) all remain valid on Symfony 8.1; section 8 adds the 8.1 debug-tooling additions.

Reference scaffolding: `src/Controller/Example/RoutingExampleController.php`.

## 1. Automatic Controller Recognition

A class or method with `#[Route]` is treated as a controller automatically - no `#[AsController]` and no manual service registration required. The class becomes a service, autowired and autoconfigured as a controller, even if it does not extend `AbstractController`.

```php
#[Route('/orders', name: 'orders_')]
final readonly class OrdersController
{
    #[Route('/{id}', name: 'show')]
    public function show(int $id): Response { /* ... */ }
}
```

Rule: prefer **class-level `#[Route]` with `name:` prefix** + method-level `#[Route]` with the rest. This keeps URL prefixes and route name prefixes in lockstep and avoids the boilerplate of writing `name: 'orders_show'` on every action.

## 2. Route Aliases (`alias:`)

Use during refactors to keep an OLD route name reachable without breaking external references (mail templates, third-party redirects, scheduled jobs):

```php
#[Route(
    '/articles/{slug}',
    name: 'article_show',
    alias: ['legacy_article_show', 'blog_post_show'],
)]
public function showArticle(string $slug): Response { /* ... */ }
```

Rules:

1. **Aliases are a transition tool, not a long-term API.** Keep them in code for ONE release window after the rename, then remove them. Carrying dead aliases forever defeats the point of the rename.
2. **Generate URLs with the canonical name.** New code MUST call `$router->generate('article_show', [...])`. The alias is only kept reachable so OLD callers still work.
3. **Document each alias.** Add a `// removed in vX.Y` comment next to the alias array; without it, future you cannot decide when to drop the alias.

## 3. Parameter Aliases (`{routePlaceholder:propertyPath}`)

Solves a real bug: when `EntityValueResolver` resolves two entities in the same route by a property of the same name, the placeholders collide. The syntax lets each placeholder bind to a specific entity-property path:

```php
#[Route('/articles-by/{authorName:author.username}/reviewed-by/{reviewerName:reviewer.username}')]
public function search(string $authorName, string $reviewerName): Response { /* ... */ }
```

Without aliases, `{username}` would have to appear twice, and Symfony would not know which `Author#username` vs `Reviewer#username` each occurrence refers to.

Rules:

1. **Use ONLY when EntityValueResolver is involved.** For plain scalar parameters, regular `{name}` is enough - parameter aliases add cognitive overhead that pays off only against the entity resolver collision.
2. **The placeholder name (left side) is what the controller argument expects.** `$authorName` matches `{authorName:...}`, not `{author.username}`.
3. **Document the entity hop.** Either write a PHPDoc `@param` line explaining the resolved entity, OR make the controller argument type-hint the entity directly (`public function search(#[MapEntity(...)] Article $article)`).

## 4. `Requirement` Enum Constants

Prefer the named constants over hand-rolled regex strings. Reads better in code reviews; harder to typo.

```php
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/users/{id}', requirements: ['id' => Requirement::POSITIVE_INT])]
#[Route('/blog/{page}', requirements: ['page' => Requirement::DIGITS])]
#[Route('/uuid/{token}', requirements: ['token' => Requirement::UUID])]
#[Route('/mongo/{id}', requirements: ['id' => Requirement::MONGODB_ID])]
```

Common constants: `DIGITS`, `POSITIVE_INT`, `DATE_YMD`, `UUID`, `UUID_V4`, `UUID_V7`, `ULID`, `ASCII_SLUG`, `CATCH_ALL`, `LOCALE`, `MONGODB_ID`.

## 5. Nested Query Strings - `#[MapQueryString(key: ...)]`

The `key:` option maps a NESTED slice of the query string into a DTO:

| URL | Code | Result |
|---|---|---|
| `?search[name]=foo&search[type]=bar` | `#[MapQueryString(key: 'search')] SearchFilter $search` | `$search->name === 'foo'`, `$search->type === 'bar'` |
| `?page=2` | `int $page = 1` (no attribute) | `$page === 2` |

Combined example:

```php
#[Route('/search')]
public function search(
    #[MapQueryString(key: 'search')] SearchFilter $search = new SearchFilter(),
    int $page = 1,
): Response { /* ... */ }
```

Rules:

1. **DTO MUST have a default-value-friendly constructor.** Either a no-arg constructor OR all parameters with defaults. The fallback default in the controller signature must instantiate without arguments.
2. **DTOs for `MapQueryString` are `final readonly`** with constructor property promotion. Keep them in `src/Dto/` if reused, OR inline at the bottom of the controller file when single-use.
3. **Do NOT add validation constraints inside the DTO** - `MapQueryString` accepts a `validationFailedStatusCode:` option for that. Constraints go via `#[Assert\*]` on the DTO properties, validated only when `validationFailedStatusCode:` is set. (8.1 also lets `validationGroups` be computed dynamically - see `request-payload-mapping.md` §5.)
4. **Type the parameter as `?DTO` or with a default `new DTO()`.** Symfony does NOT inject an empty DTO automatically; without a default, missing `search[...]` triggers a 400. (8.1 adds `mapWhenEmpty: true` to force denormalization on empty input - see `request-payload-mapping.md` §4.)

## 6. `env:` Array on `#[Route]`

Restrict a route to specific Symfony environments. The `env:` option accepts an array (previously a single string).

```php
#[Route('/_debug/mail-preview', name: 'debug_mail_preview', env: ['dev', 'test'])]
public function mailPreview(): Response { /* ... */ }

#[Route('/healthcheck', name: 'healthcheck', env: ['staging', 'prod'])]
public function healthcheck(): Response { /* ... */ }
```

Rules:

1. **Use for environment-specific endpoints**, not for security controls. A `dev`-only route is a convenience - do NOT rely on it for keeping secrets out of production. Use `#[IsGranted]` for real access control.
2. **Pair `dev` with `test`** when the route is also exercised by functional tests. `env: 'dev'` alone hides the route from the test container.
3. **Keep this list TIGHT.** A route active in 3-4 environments suggests it should just exist everywhere and rely on `#[IsGranted]` for access; the env filter is for routes that DO NOT make sense outside specific contexts (debug tools, internal probes).

## 7. Route Attribute Auto-Registration (`routing.controllers` tag)

A `routing.controllers` service tag auto-collects every class with `#[Route]`. Simplifies the routes loader:

```yaml
# config/routes.yaml (NEW form)
controllers:
    resource: routing.controllers
```

Replaces the older path-based form:

```yaml
# OLD - still works, but less robust to namespace changes
controllers:
    resource: ../src/Controller/
    namespace: App\Controller
    type: attribute
```

This stub currently uses the OLD form (see `config/routes.yaml`). Migrate when convenient - the new form is path-agnostic, so renaming `src/Controller/` to `src/Http/` does not require a routes config edit.

## 8. Symfony 8.1 Debug-Tooling Additions

Both additions below are debug-console improvements - additive, no runtime routing change.

### 8.1 `debug:router --sort`

8.1 adds a `--sort` option to `debug:router`. Without it, routes print in registration order, which makes a specific route hard to find in a large table. `--sort` orders the listing so you can scan by name / path instead of hunting through definition order.

```bash
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console debug:router --sort
```

Use it whenever the route table is large enough that registration order is not the order you want to read. The existing `debug:router <name>` (single-route detail) and the plain `debug:router` (registration order) forms are unchanged.

### 8.2 Decoration Stack in `debug:container`

8.1 adds the decoration stack to the `debug:container` command output: when a service is decorated (including the service-stack and tag-decoration mechanisms documented in `dependency-injection.md` §5-§6), `debug:container <service-id>` now shows the full decorator chain wrapping it. This is a diagnostics aid for "which decorators wrap this service and in what order" - not a routing feature, but it lives in the same FrameworkBundle debug-console surface and is useful when a route's controller or its dependencies are decorated.

```bash
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console debug:container <service-id>
```

## 9. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Route name "orders_show" already in use` | Same route name on a method AND on the parent class (without name prefix) | Use class-level `name: 'orders_'` prefix + short method-level names. |
| `Cannot resolve parameter "authorName"` | Parameter alias typo (`{author:username}` instead of `{authorName:author.username}`) | Form is `{placeholder:property.path}` - placeholder MUST match the controller argument name. |
| `Argument "$search" must be of type SearchFilter, null given` | `#[MapQueryString(key: ...)]` parameter lacks a default | Add `= new SearchFilter()` (or `?SearchFilter $search = null`). |
| `Unknown enum case Requirement::MONGODB_ID` | `symfony/routing` < 7.3 | Upgrade `symfony/routing` to `^7.3`. |
| `debug:router` has no `--sort` option | `symfony/framework-bundle` < 8.1 | Upgrade `symfony/framework-bundle` to `^8.1`. |

## 10. Version Constraints

| Package | Required |
|---|---|
| `symfony/routing` | `^7.3` (for parameter aliases, `Requirement::MONGODB_ID`, `alias:` in PHP attribute form), `^7.4` (for `env: [...]` array) |
| `symfony/http-kernel` | `^7.3` (for `MapQueryString(key: ...)`) |
| `symfony/framework-bundle` | `^7.3` (for automatic controller recognition from class-level `#[Route]`), `^7.4` (for `routing.controllers` service tag), `^8.1` (for `debug:router --sort` and the decoration stack in `debug:container`) |
