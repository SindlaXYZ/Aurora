# Rate Limiter - Compound Policy, `#[RateLimit]` Attribute, Calendar-Aligned Windows

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/rate_limiter.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Attribute/RateLimit.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/RateLimiter/Policy/FixedWindowLimiter.php

The built-in `compound` policy (combine multiple rate limiters into one, no custom PHP) was introduced in the 7.3 line and is **unchanged on 8.1** (sections 1-4). Symfony 8.1 adds two features documented here: the declarative `#[RateLimit]` controller attribute (section 5) and a calendar-aligned window mode for `FixedWindowLimiter` (section 6).

## 1. When to Use Compound Policies

Common combinations:

- **Burst + sustained:** "max 5 per minute AND max 100 per hour" - protects against burst attacks AND sustained abuse.
- **Per-user + per-IP:** combine identical limits keyed differently to block both account-level and network-level abuse.
- **Free + premium:** a single endpoint with two tiers, picked by the consumer's authentication level.

Skip compounds when one window is enough - they add a second lock acquisition per request.

## 2. Configuration

```yaml
# config/packages/framework.yaml
framework:
    rate_limiter:
        two_per_minute:
            policy: 'fixed_window'
            limit: 2
            interval: '1 minute'
        five_per_hour:
            policy: 'fixed_window'
            limit: 5
            interval: '1 hour'

        # The compound combines the two above.
        contact_form:
            policy: 'compound'
            limiters: [two_per_minute, five_per_hour]
```

## 3. Usage

```php
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class ContactController
{
    public function __construct(private RateLimiterFactoryInterface $contactFormLimiter) {}

    public function submit(Request $request): Response
    {
        $limiter = $this->contactFormLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            return new Response('Too many requests', 429);
        }

        // ...
    }
}
```

The compound limiter exposes the SAME `RateLimiterFactoryInterface` API as a regular limiter - drop-in replacement. (Type-hint `RateLimiterFactoryInterface`, NOT the concrete `RateLimiterFactory` - the concrete-class autowiring aliases were removed in Symfony 8.0.)

## 4. Rules

1. **Order the limiters tightest-first.** When the compound consumes a token, it consumes from EVERY child. Placing the strictest window first short-circuits faster on rejection.
2. **All child limiters MUST exist.** A typo in `limiters: [...]` fails at container compile time.
3. **Lock store matters for accuracy.** Set `lock_factory: lock.factory` (or a Redis-backed lock) on each child limiter for multi-server deployments. The default in-memory lock is fine for single-container DEV.
4. **Use Symfony's `login_throttling:` for auth endpoints.** That feature is already a configurable rate limiter under the hood; do NOT duplicate it with a custom compound on the same route. See `.claude/rules/security.md` section 4.
5. **Surface the right error.** When `isAccepted() === false`, return HTTP 429 and include `Retry-After:` derived from `$limiter->consume()->getRetryAfter()`.

## 5. Symfony 8.1 - `#[RateLimit]` Controller Attribute

The new attribute applies rate limiting to a controller declaratively, removing the inject-factory + `create()` + `consume()` + `isAccepted()` boilerplate from section 3. The framework enforces the limit BEFORE the controller runs and, when the limit is exceeded, throws `TooManyRequestsHttpException`, which Symfony turns into a `429 Too Many Requests` response with a `Retry-After` header.

### 5.1 Class, target, and namespace

The attribute is `Symfony\Component\HttpKernel\Attribute\RateLimit` (it lives in the HttpKernel component, NOT the RateLimiter component, because it operates on the controller layer). It is **repeatable** and targets a class, a method, or a function:

```php
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
```

### 5.2 Constructor signature

```php
public function __construct(
    public readonly string $limiter,                            // 1st positional: the rate_limiter name
    public readonly string|Expression|\Closure|null $key = null,
    public readonly int $tokens = 1,
    array|string $methods = [],
)
```

- **`$limiter`** (first positional) - the name of a limiter declared under `framework.rate_limiter` (the same names used in sections 1-3).
- **`$key`** - what the limiter bucket is keyed on. When omitted (`null`), the key is the client IP + HTTP method + path. Provide an `Expression` or a `Closure` to bucket by something else (e.g. an email field).
- **`$tokens`** - how many tokens this request consumes (default `1`). Raise it for expensive endpoints.
- **`$methods`** - restrict the check to specific HTTP verbs; empty = all verbs.

### 5.3 Usage

```php
// src/Controller/ApiController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;

class ApiController extends AbstractController
{
    // No 'key' -> bucket keyed on client IP + HTTP method + path.
    #[RateLimit('api')]
    public function index(): JsonResponse
    {
        // ...
    }

    // Restrict the check to write verbs only.
    #[RateLimit('api', methods: ['POST', 'PUT', 'PATCH', 'DELETE'])]
    public function edit(): JsonResponse
    {
        // ...
    }

    // Bucket per account using an Expression over the request.
    #[RateLimit('per_account', key: new Expression('request.request.get("email")'))]
    public function resetPassword(): Response
    {
        // ...
    }

    // Same, with a closure (better static analysis than the string Expression).
    #[RateLimit('per_account', key: fn (array $args, Request $request): string => $request->query->get('email'))]
    public function resetPasswordViaLink(): Response
    {
        // ...
    }

    // Consume more than one token for an expensive endpoint.
    #[RateLimit('api', tokens: 5)]
    public function export(): JsonResponse
    {
        // ...
    }
}
```

### 5.4 How it fits the dynamic-attribute machinery

`#[RateLimit]` is a controller attribute enforced on the `kernel.controller_arguments` phase, so it participates in the Symfony 8.1 dynamic-controller-attributes system - the `key` Expression / Closure is resolved through the same `evaluate()` path as the `#[Cache]` `if` / `etag` values (the expression context exposes `request`, `args`, and `this`). See `.claude/rules/dynamic-controller-attributes.md` (sections 5-6) and `.claude/rules/cache-attribute.md`. Because the attribute is repeatable, several `#[RateLimit]` blocks can stack on one action (e.g. a per-IP limiter plus a per-account limiter).

### 5.5 Rules

1. **The `$limiter` name MUST be a declared `framework.rate_limiter` entry.** The attribute references a limiter; it does not define one. A typo'd name fails when the attribute fires.
2. **Prefer a `Closure` over an `Expression` for a non-trivial key.** The closure is type-checked by PHPStan / Psalm and survives IDE refactors; the string Expression is invisible to them. Use the Expression only for a one-liner.
3. **Use the attribute for plain Symfony controllers, NOT API Platform resources.** API Platform routes through its own state pipeline and never triggers the controller-attribute machinery - rate-limit those at the limiter-service level (section 3) or via a custom extension. The `#[RateLimit]` attribute belongs on controllers under `src/Controller/`. (Same boundary as the other controller attributes - see `cache-attribute.md` section, `serialize-attribute.md`.)
4. **Set `methods:` when only writes should be limited.** A GET that a human clicks and a POST callback often need different policies - restrict the check rather than splitting the action.
5. **Do not re-wrap the 429.** The attribute already produces `429 Too Many Requests` + `Retry-After`. Let it; only drop back to the manual `consume()` form (section 3) when you need a non-429 response or custom body.

## 6. Symfony 8.1 - Calendar-Aligned `FixedWindowLimiter`

By default a fixed window starts on the first hit and resets one `interval` later, so the window boundaries float. Symfony 8.1 adds a calendar-aligned mode: the window is anchored to a fixed datetime and resets every `interval` from there, giving boundaries that line up with the calendar (e.g. the top of every hour, midnight every day) regardless of when the first hit lands.

The mode is enabled by the `FixedWindowLimiter` constructor's new `?\DateTimeImmutable $anchorAt = null` argument:

```php
public function __construct(
    string $id,
    private int $limit,
    \DateInterval $interval,
    StorageInterface $storage,
    ?LockInterface $lock = null,
    private readonly ?\DateTimeImmutable $anchorAt = null,   // 8.1 NEW
)
```

Per the source: "When set, the window is aligned to a calendar starting at this datetime and resetting every `$interval`, instead of starting on the first hit." Leave it `null` (the default) for the classic first-hit-starts-the-window behavior.

Use the aligned mode when the limit must mean "N per calendar hour / day" rather than "N per rolling interval since the first request" - e.g. a daily quota that everyone sees reset at midnight, or hourly buckets that line up with reporting windows. Skip it when a rolling window is what you actually want (most abuse-prevention limits).

## 7. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Unknown rate limiter "..."` in a compound (or on `#[RateLimit('...')]`) | Typo in `limiters: [...]` or in the attribute's `$limiter` name | Match a key declared in the same `framework.rate_limiter` block. |
| Tokens consumed twice per request | Application code calls `consume()` more than once on the compound | Call once per attempt; `consume()` mutates state. |
| Limit not enforced across containers | In-memory lock store | Switch the lock factory to Redis / Postgres in `framework.lock`. |
| `#[RateLimit]` has no effect / `Attribute class "RateLimit" not found` | `symfony/http-kernel` < 8.1, or the wrong namespace imported | Upgrade to `^8.1`; import `Symfony\Component\HttpKernel\Attribute\RateLimit` (HttpKernel, not RateLimiter). |
| Window does not reset on the calendar boundary you expected | `anchorAt` left `null` (rolling window) | Construct the `FixedWindowLimiter` with an `anchorAt` datetime to align the window. |

## 8. Version Constraints

| Package | Required |
|---|---|
| `symfony/rate-limiter` | `^8.1` (for the calendar-aligned `FixedWindowLimiter` `anchorAt`; the `compound` policy is carried from `^7.3`. Already installed by `symfony_install_skeleton()`) |
| `symfony/http-kernel` | `^8.1` (for the `#[RateLimit]` controller attribute) |
| `symfony/lock` | `^7.3` (already installed; required for cross-server enforcement) |
| `symfony/expression-language` | required only when a `#[RateLimit(key: new Expression(...))]` is used |
