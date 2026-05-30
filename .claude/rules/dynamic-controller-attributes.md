# Dynamic Controller Attributes

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-13 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-dynamic-controller-attributes
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ControllerEvent.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ControllerArgumentsEvent.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ControllerArgumentsMetadata.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ControllerMetadata.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ControllerAttributeEvent.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Event/ResponseEvent.php

Symfony 8.1 makes controller attributes (`#[Cache]`, `#[IsGranted]`, `#[Template]`, `#[MapRequestPayload]`, custom ones) **runtime-overrideable**. Before 8.1, attributes were read from source via reflection on each call - listeners could observe but not modify them. Since 8.1, attributes are stored in a request attribute (`_controller_attributes`), a `ControllerEvent` listener can replace them for the current request, and each attribute class triggers its OWN dedicated kernel event for cleaner listener wiring.

## 1. What Changed in Symfony 8.1

All of these changes are **additive** - existing 8.0 listeners that read attributes via reflection keep working.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Where attributes live at runtime | Re-read from reflection on every read | Stored once in `Request::$attributes->get('_controller_attributes')`, reused thereafter |
| Replace attributes per-request | Not possible - reflection is read-only | `ControllerEvent::setController($controller, $attributes)` accepts new attributes as second argument |
| Iterate all attributes flat (in declaration order) | DIY merge of grouped array | `ControllerEvent::getAttributes('*')` |
| Access attributes from `ResponseEvent` | Not exposed - needed to re-walk the controller via reflection | `ResponseEvent::$controllerMetadata?->getAttributes(...)` (a `ControllerArgumentsMetadata`) |
| Evaluate an attribute's `Expression`/`Closure` value | DIY - resolve the `ExpressionLanguage` and named args by hand | `ControllerEvent::evaluate()` / `ControllerArgumentsEvent::evaluate()` / `ControllerArgumentsMetadata::evaluate()` / `ControllerAttributeEvent::evaluate()` |
| Listener for a specific attribute class | One global `kernel.controller` listener with `instanceof` checks | Dedicated event `kernel.controller_arguments.{AttributeFQCN}` + `ControllerAttributeEvent` |

## 2. Storage and Read API - `ControllerEvent::getAttributes()`

Attributes are now resolved by reflection on the **first** call to `getAttributes()` and stored in the `_controller_attributes` request attribute. Subsequent calls reuse the stored value. This means:
- A listener that runs BEFORE the first read can mutate the source-of-truth array.
- A listener that runs AFTER the first read must call `setController()` with a new attributes array to take effect.

Three call forms, all on `Symfony\Component\HttpKernel\Event\ControllerEvent`:

```php
// 1. Grouped by attribute class (default behavior, same as 8.0)
$grouped = $event->getAttributes();
// [Cache::class => [new Cache(...)], IsGranted::class => [new IsGranted(...)], ...]

// 2. Flat list in source-order (NEW in 8.1)
$ordered = $event->getAttributes('*');
// [new Cache(...), new IsGranted(...), new Cache(...)]

// 3. Filtered by attribute class (NEW in 8.1)
$caches = $event->getAttributes(Cache::class);
// [new Cache(...), new Cache(...)]
```

Use `'*'` only when declaration order matters (e.g., precedence between two repeated attributes). Use the class-filtered form for typed reads - it spares the caller an `isset`/`instanceof` check.

## 3. Replacing Attributes for a Single Request

Override `#[Cache]` only for the current request - without editing the controller source:

```php
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ForceShortCacheForBetaUsers
{
    public function __invoke(ControllerEvent $event): void
    {
        if (!$this->isBetaUser($event->getRequest())) {
            return;
        }

        $attributes = $event->getAttributes();
        $attributes[Cache::class] = [new Cache(maxage: 60, public: true)];

        $event->setController(
            $event->getController(),
            array_merge(...array_values($attributes)),
        );
    }
}
```

Key points:
- Pass the SAME controller as the first arg (you're only replacing attributes, not the action).
- The second arg expects a **flat list** of attribute instances (not the grouped form) - hence `array_merge(...array_values($attributes))`.
- Removing an attribute is symmetric: `unset($attributes[Cache::class])` before flattening.

## 4. Reading Controller Attributes in `ResponseEvent`

`Symfony\Component\HttpKernel\Event\ResponseEvent` now exposes a public readonly property `$controllerMetadata` typed `?ControllerArgumentsMetadata` (nullable - null for sub-requests / non-controller responses, e.g. a response produced in `kernel.request`). `Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata` is the read-only metadata holder; it carries the same `getAttributes()` API plus `getArguments()`, `getNamedArguments()`, and `evaluate()` (see §6). The most common use case is conditionally setting response headers based on the attribute declared on the action:

```php
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class CacheTelemetryListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $caches = $event->controllerMetadata?->getAttributes(Cache::class) ?? [];
        if ([] === $caches) {
            return;
        }

        $event->getResponse()->headers->set('X-Cache-Policy-Count', (string) \count($caches));
    }
}
```

Same `getAttributes()` API as in `ControllerEvent` - pass an FQCN, `'*'`, or nothing.

## 5. Dedicated Per-Attribute Events (NEW)

Instead of writing one listener for `kernel.controller` / `kernel.controller_arguments` and dispatching on `instanceof`, Symfony 8.1 emits a **dedicated event per attribute class**:

- Event name pattern: `{kernelEvent}.{AttributeFQCN}`
- Concrete example: `kernel.controller_arguments.Symfony\Component\HttpKernel\Attribute\Cache`
- Event class delivered to the listener: `Symfony\Component\HttpKernel\Event\ControllerAttributeEvent`

Each `#[Cache]` declared on the action dispatches its OWN event (one per attribute instance, in source order). The same applies to repeatable attributes - three `#[Cache(...)]` on one action means three events.

### Custom attribute + listener - end-to-end example

```php
// src/Attribute/RateLimit.php
namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class RateLimit
{
    public function __construct(
        public int $maxRequests   = 100,
        public int $periodSeconds = 60,
    ) {
    }
}
```

```php
// src/EventListener/RateLimitListener.php
namespace App\EventListener;

use App\Attribute\RateLimit;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS . '.' . RateLimit::class)]
final class RateLimitListener
{
    public function __invoke(ControllerAttributeEvent $event): void
    {
        /** @var RateLimit $rateLimit */
        $rateLimit = $event->attribute;
        $request   = $event->kernelEvent->getRequest();

        // ... enforce $rateLimit->maxRequests within $rateLimit->periodSeconds for $request
    }
}
```

```php
// src/Controller/SomeController.php
use App\Attribute\RateLimit;

final class SomeController
{
    #[RateLimit(maxRequests: 10, periodSeconds: 1)]
    public function __invoke(): Response
    {
        // ...
    }
}
```

`ControllerAttributeEvent` properties and methods:
- `$event->attribute` - the **single** attribute instance that triggered this dispatch (a `readonly object`, typed by your attribute class when you read it).
- `$event->kernelEvent` - the underlying `KernelEvent` (a `ControllerEvent` or `ControllerArgumentsEvent`), exposing `getRequest()`, `getController()`, `setController()`, `getAttributes()`, etc.
- `$event->evaluate($value, $expressionLanguage = null)` - resolve an `Expression`/`Closure` attribute value against the controller's named arguments (see §6).

### When to use dedicated events vs a `ControllerEvent` listener

| Need | Use |
|---|---|
| React to a SPECIFIC attribute class | Dedicated event (no `instanceof`, no manual fetch) |
| React to multiple unrelated attributes in one listener | `ControllerEvent` listener + `getAttributes()` |
| Modify or replace attributes globally | `ControllerEvent` listener with `setController(..., $attrs)` |
| Read attributes during response phase | `ResponseEvent::$controllerMetadata` |

## 6. Evaluating `Expression` / `Closure` Attribute Values - `evaluate()`

Attributes increasingly carry values that are an `Expression` or a `Closure` rather than a literal - for example the 8.1 `#[Cache(if: ...)]` / `#[Cache(etag: ...)]` (see `cache-attribute.md`) or a custom attribute of your own. A listener that wants to resolve such a value needs the right `ExpressionLanguage` and the controller's resolved named arguments as the evaluation context. Symfony 8.1 packages that into a single `evaluate()` method, available on all four types:

- `ControllerEvent::evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage, array $args = []): mixed`
- `ControllerArgumentsEvent::evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage): mixed`
- `ControllerArgumentsMetadata::evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage): mixed`
- `ControllerAttributeEvent::evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage = null): mixed`

Behavior: if `$value` is a `Closure` it is invoked as `$value($args, $request, $controller)`; if it is an `Expression` it is evaluated with `args`, `request`, and `this` (the controller) as variables; otherwise the value is returned unchanged. `$args` is the controller's resolved named-arguments array (keyed by parameter name). This means a listener can treat literal, `Expression`, and `Closure` attribute values uniformly.

```php
use App\Attribute\FeatureGate;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS . '.' . FeatureGate::class)]
final class FeatureGateListener
{
    public function __invoke(ControllerAttributeEvent $event): void
    {
        /** @var FeatureGate $gate */
        $gate = $event->attribute;

        // $gate->enabled may be a bool, an Expression, or a Closure(array $args): bool.
        // evaluate() resolves all three against the controller's named arguments.
        $enabled = $event->evaluate($gate->enabled);

        if (false === $enabled) {
            // ... deny / short-circuit
        }
    }
}
```

Pass your own `ExpressionLanguage` only when the attribute's expressions need custom functions; otherwise let the framework supply the default one (the `ControllerAttributeEvent` form defaults the argument to `null`).

## 7. Use Cases

- **Tenant-scoped cache overrides** - replace `#[Cache]` per request based on the authenticated user/tenant without touching every controller.
- **Conditional security** - strip a `#[IsGranted]` attribute for impersonation flows or maintenance bypass; add `#[IsGranted]` dynamically for paths matched at runtime.
- **Centralized rate limiting** - declarative `#[RateLimit]` on actions, single listener enforces it (replaces the old "controller decorator + service locator" pattern).
- **Feature-flagged behavior** - strip `#[Template]` from a controller when an API client requests JSON (combine with `#[Serialize]` from the v8.1 stub).
- **Response telemetry** - read `#[Cache]` / custom attributes from `ResponseEvent` to emit metrics about declared policies vs effective output.

## 8. Relation to API Platform (Dockraft stub context)

The Dockraft v8.1 stub uses API Platform 4.3, which routes through its own state-provider/state-processor pipeline rather than plain controllers. Dynamic controller attributes:

- **DO apply** to plain Symfony controllers under `src/Controller/` and to API Platform custom controllers declared via `controller:` on an `#[ApiResource]` operation.
- **DO NOT apply** to API Platform state providers/processors - those are not "controllers" in `HttpKernel` terms and never trigger `ControllerEvent`. Use API Platform's own extension points (`ProviderInterface`, `ProcessorInterface`, normalization context) for behavior changes there.

## 9. Anti-patterns

- **Mutating `getAttributes()`'s returned array in place and expecting it to stick** - the array is a copy of the stored attributes. You MUST call `setController($controller, $flatAttributes)` for changes to take effect.
- **Forgetting `array_values()` when flattening grouped attributes for `setController()`** - `array_merge(...$grouped)` with string keys silently drops the wrong entries. Always flatten via `array_merge(...array_values($attributes))`.
- **Listening on the dedicated event AND modifying attributes via `setController()` inside it** - the kernel iterates the attribute list while dispatching dedicated events; mutating the list mid-iteration produces undefined order of subsequent dispatches. If you need to modify attributes, do it from a regular `ControllerEvent` listener with higher priority than the dedicated dispatchers.
- **Reading `ResponseEvent::$controllerMetadata` without the null check** - it's null for sub-requests, error responses outside the controller phase, and responses originating in `kernel.request`. Always use `?->` and `?? []`.
- **Reading attributes via reflection in 8.1 listeners** - the cached `_controller_attributes` is the source of truth. Re-reading via reflection bypasses any earlier listener that modified attributes for the current request.
- **Building custom dispatch logic on top of `kernel.controller`** with an internal `instanceof` chain - that's exactly what the dedicated per-attribute events replace. Use `KernelEvents::CONTROLLER_ARGUMENTS . '.' . AttrClass::class` instead.

## 10. Migration Notes (8.0 to 8.1)

Nothing forced. Existing controller attribute listeners keep working unchanged.

Voluntary cleanups when touching listener code for other reasons:

1. **Replace reflection-based attribute reads** with `$event->getAttributes()` - same result, no `ReflectionMethod` instantiation.
2. **Split a giant `kernel.controller_arguments` listener** with multiple `instanceof` branches into N dedicated `ControllerAttributeEvent` listeners - one per attribute class. Smaller scope, easier to test.
3. **Move post-response attribute reads** from a manual reflection walk to `ResponseEvent::$controllerMetadata?->getAttributes(...)`.

No deprecations on the 8.0 APIs. Adopt incrementally per file you touch.

## 11. Quick Reference

| API | Returns / Purpose | When to use |
|---|---|---|
| `ControllerEvent::getAttributes()` | Grouped: `[FQCN => [instances]]` | Default read |
| `ControllerEvent::getAttributes('*')` | Flat list in source order | Order-dependent processing |
| `ControllerEvent::getAttributes(MyAttr::class)` | Filtered instances of `MyAttr` | Targeted read without `instanceof` |
| `ControllerEvent::setController($controller, $attrs)` | Replace attributes for this request | Per-request override / removal |
| `ControllerEvent::evaluate($value, $el)` | Resolve an `Expression`/`Closure` attribute value against named args | Computing a dynamic attribute value in a listener |
| `ResponseEvent::$controllerMetadata` | Nullable `ControllerArgumentsMetadata` | Read attributes (`getAttributes(...)`) during response phase |
| `KernelEvents::CONTROLLER_ARGUMENTS . '.' . MyAttr::class` | Event name for dedicated dispatch | `#[AsEventListener(event: ...)]` for per-attribute listeners |
| `ControllerAttributeEvent::$attribute` | The single attribute instance | Inside a dedicated listener |
| `ControllerAttributeEvent::$kernelEvent` | The underlying `KernelEvent` (`ControllerEvent` / `ControllerArgumentsEvent`) | Access request / controller from a dedicated listener |
| `ControllerAttributeEvent::evaluate($value, $el = null)` | Resolve an `Expression`/`Closure` value against named args | Inside a dedicated listener for an attribute with dynamic values |
