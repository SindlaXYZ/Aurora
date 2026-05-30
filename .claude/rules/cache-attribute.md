# `#[Cache]` HTTP Cache Attribute

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-12 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-improved-cache-attribute
* https://symfony.com/doc/8.1/http_cache.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Attribute/Cache.php

The `Symfony\Component\HttpKernel\Attribute\Cache` attribute declares HTTP cache headers (`Cache-Control`, `ETag`, `Last-Modified`, `Vary`) directly on controller actions. Symfony 8.1 adds three additive improvements: explicit `request` / `args` variables in expressions, closure support for `etag` / `lastModified`, and a new `if` parameter - plus the attribute is now **repeatable** on the same action.

## 1. What Changed in Symfony 8.1

All three improvements are **additive** - no breaking changes. Existing 8.0 code keeps working.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Expression variables for `etag` / `lastModified` | Flat variables - request attributes merged with controller arguments (name clashes possible; `Request` object unreachable) | **`request`** (the `Request` object) and **`args`** (resolved controller arguments array) exposed explicitly. Old flat variables remain available for backward compat |
| Closure values for `etag` / `lastModified` | Strings only (expression language) | Strings OR closures `static fn (array $args, Request $request): ... => ...` |
| Conditional application | Not supported - attribute always applied | **New `if` parameter** (string expression or closure returning bool) - skip the attribute when the condition is false |
| Multiple `#[Cache]` on one action | Not allowed (single attribute only) | **Repeatable** - declare several `#[Cache]` blocks gated by different `if` conditions |

## 2. Use Case - Expression Variables (`request` + `args`)

```php
use Symfony\Component\HttpKernel\Attribute\Cache;

#[Cache(
    etag        : "args['article'].computeETag()",
    lastModified: "args['article'].getUpdatedAt()",
    public      : true,
)]
public function show(Article $article): Response
{
    // ...
}
```

The `args` array is keyed by controller parameter name (`'article'` here). The expression context also exposes `this` (the controller instance) alongside `request` and `args`. Use `request` to mix HTTP request data into the cache key:

```php
#[Cache(etag: "request.headers.get('Accept-Language') ~ args['article'].getId()")]
public function show(Article $article): Response
{
    // ETag varies per Accept-Language header - proper content-negotiation caching
}
```

**Why this matters:** in 8.0 the expression `article.computeETag()` would silently break if a request attribute also named `article` existed. The new explicit namespace eliminates the ambiguity.

## 3. Use Case - Closures (better static analysis)

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;

#[Cache(
    lastModified: static function (array $args, Request $request): \DateTimeInterface {
        return $args['post']->getUpdatedAt();
    },
    etag: static function (array $args, Request $request): string {
        return (string) $args['post']->getId();
    },
)]
public function show(Post $post): Response
{
    // ...
}
```

Prefer closures over string expressions when:
- The logic is non-trivial (multiple calls, conditionals, formatting).
- You want PHPStan / Psalm to type-check the cache key construction.
- IDE refactor (rename property / method) needs to update the cache logic too - strings are invisible to refactor tools.

Signature contract: closures are invoked as `$closure($args, $request, $controller)` - the full signature is `(array $args, Request $request, ?object $controller)`, and you declare only the parameters you use (the 2-arg form above is the idiomatic one). They return whatever the parameter expects (`\DateTimeInterface` for `lastModified`, `string` for `etag`, `bool` for `if`). The values are resolved through the same `evaluate()` path as any controller-attribute expression/closure (see `dynamic-controller-attributes.md` §6).

## 4. Use Case - Conditional `if`

The most impactful addition. Skip the entire `#[Cache]` block when a runtime condition is false:

```php
#[Cache(
    public : true,
    maxage : 3600,
    if     : static fn (array $args, Request $request): bool => !$request->query->has('preview'),
)]
public function show(Request $request): Response
{
    // Caches publicly for 1h - UNLESS the URL contains ?preview=...
    // Preview URLs render fresh content (no cache hit, no cache write).
}
```

Common patterns:
- **Preview / draft mode:** skip cache when a `preview` query param or `X-Preview` header is present.
- **Feature flags:** apply cache only when a feature is in stable rollout, not behind a flag.
- **Authenticated vs anonymous:** different cache lifetimes (see §5).
- **Non-`Response` controllers:** apply cache only when downstream view layer will produce a cacheable response.

## 5. Use Case - Repeatable Attribute (multiple cache strategies)

The classic split: anonymous users get a long shared cache; authenticated users get a short private cache.

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;

#[Cache(
    public : true,
    maxage : 3600,
    smaxage: 7200,
    if     : static fn (array $args, Request $request): bool => !$request->query->has('preview'),
)]
#[Cache(
    public : false,
    maxage : 0,
    if     : static fn (array $args, Request $request): bool => $request->query->has('preview'),
)]
public function article(Request $request): Response
{
    // Two distinct cache policies on the same action, selected at runtime by `if`.
}
```

The first matching `if` wins. Order matters - declare more specific conditions before catch-alls.

## 6. Parameter Reference

The actual `Cache` constructor parameters are (`?bool $public`, `?bool $noStore`, `bool $mustRevalidate`, `?string $expires`, `int|string|null $maxage`, `int|string|null $smaxage`, `int|string|null $maxStale`, `int|string|null $staleWhileRevalidate`, `int|string|null $staleIfError`, `array $vary`, `\DateTimeInterface|string|Expression|\Closure|null $lastModified`, `string|Expression|\Closure|null $etag`, `bool|string|Expression|\Closure $if`). There is **no** `private` parameter - mark a response private with `public: false`. The most-used ones:

| Parameter | Type | Maps to HTTP | Notes |
|---|---|---|---|
| `public` | `?bool` | `Cache-Control: public` / `private` | `true` -> `public` (shared caches: CDN, Varnish, browser); `false` -> `private` (browser only). There is no separate `private` parameter |
| `maxage` | `int\|string` (seconds) | `Cache-Control: max-age=N` | Lifetime for private/end-client caches |
| `smaxage` | `int\|string` (seconds) | `Cache-Control: s-maxage=N` | Lifetime for shared caches (overrides `maxage` for CDNs) |
| `expires` | `string` | `Expires: <date>` | Legacy; prefer `maxage`/`smaxage` |
| `etag` | `string\|Expression\|Closure` | `ETag: <hash>` | Conditional GET via `If-None-Match` |
| `lastModified` | `\DateTimeInterface\|string\|Expression\|Closure` | `Last-Modified: <date>` | Conditional GET via `If-Modified-Since` |
| `vary` | `array` | `Vary: <headers>` | Discriminate cache by request headers |
| `mustRevalidate` | `bool` | `Cache-Control: must-revalidate` | Force revalidation when stale |
| `noStore` | `?bool` | `Cache-Control: no-store` | Disable all caching |
| `if` (**8.1+**) | `bool\|string\|Expression\|Closure` (default `true`) | - | Skip the attribute when it evaluates to false |

## 7. Interaction with Controller Response Headers

`#[Cache]` does **not** override cache headers already set on the `Response` returned by the controller. If you set `Cache-Control` manually inside the action, that value wins. Use `#[Cache]` for declarative defaults; override imperatively only when the cache strategy depends on data computed at runtime.

## 8. When to Choose Strings vs Closures vs Repeatable

| Need | Pick |
|---|---|
| Single trivial expression on an entity getter | String - `"args['post'].getUpdatedAt()"` |
| Multi-line / typed / debuggable logic | Closure |
| Cache strategy changes based on a runtime flag | `if` parameter |
| Two or more distinct strategies on the same action | Repeatable `#[Cache]` with `if` |
| Need to read request headers inside the cache key | Either - but closures with `Request $request` are more readable |

## 9. Anti-patterns

- **Mixing flat variables and `args` in the same expression** - pick one. Flat is BC-only; new code should use `args` exclusively.
- **Expecting a `private` parameter** - there is none. Use `public: false` to mark a response private; `#[Cache]` has only a `?bool $public`.
- **`maxage` on personalized responses without `public: false`** - leaks per-user content to shared caches. Always pair personalized content with `public: false` or a `Vary: Cookie` directive.
- **String expression for non-trivial logic** - once the expression has more than one method call or a conditional, switch to closure for type-safety.
- **Forgetting `Vary: Accept-Language`** when serving translated content with `public` caches - shared caches will serve the wrong language to other users.

## 10. Migration Notes (8.0 → 8.1)

No migration required. All 8.0 cache expressions keep working unchanged. Adopt the new features incrementally:

1. Replace `"article.getUpdatedAt()"` with `"args['article'].getUpdatedAt()"` when touching the code - disambiguates against request attributes.
2. Convert string expressions to closures when adding type-checked logic.
3. Add `if` instead of duplicating the action for preview/feature-flag variants.
4. Use repeatable `#[Cache]` for action-wide split strategies instead of moving the split to a service-layer cache.
