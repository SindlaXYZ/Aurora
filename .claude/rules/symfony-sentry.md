# sentry/sentry-symfony

| Version | Created    | Updated    |
|---------|------------|------------|
| 5.10    | 2026-05-30 | 2026-05-30 |

**Sources:**
* https://docs.sentry.io/platforms/php/guides/symfony/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/options/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/symfony-options/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/environments/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/releases/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/sampling/
* https://docs.sentry.io/platforms/php/guides/symfony/configuration/filtering/
* https://docs.sentry.io/platforms/php/guides/symfony/integrations/
* https://docs.sentry.io/platforms/php/guides/symfony/integrations/monolog/
* https://docs.sentry.io/platforms/php/guides/symfony/enriching-events/tags/
* https://docs.sentry.io/platforms/php/guides/symfony/enriching-events/breadcrumbs/
* https://docs.sentry.io/platforms/php/guides/symfony/enriching-events/context/
* https://docs.sentry.io/platforms/php/guides/symfony/enriching-events/identify-user/
* https://github.com/getsentry/sentry-symfony/blob/master/UPGRADE-5.0.md

`sentry/sentry-symfony` is the Symfony bundle for the Sentry error-monitoring SDK (the underlying SDK is `sentry/sentry`). It captures unhandled exceptions, errors, and (optionally) performance traces, enriches them with request / user / release context, and ships them to a Sentry project identified by a DSN. Bundle `^5.10` requires PHP `^8.0` and supports Symfony `^8.0`, so it runs unchanged on the v8.1 stub (PHP 8.5, Symfony 8.1).

## 1. Bundle Registration - prod-only

Dockraft's `symfony_install_skeleton()` (in `.docker/container/scripts/symfony.sh`) runs `composer require sentry/sentry-symfony` and then registers the bundle **for the `prod` environment only**:

```php
// config/bundles.php
return [
    // ...
    Sentry\SentryBundle\SentryBundle::class => ['prod' => true],
];
```

Bundle FQCN: `Sentry\SentryBundle\SentryBundle`. Because the bundle is `['prod' => true]`, the entire integration is inert in DEV and test - no events are sent, and `config/packages/sentry.yaml` is wrapped in `when@prod:` to match. Do NOT register it for `all` / `dev`: capturing local development errors floods the Sentry project and burns quota. If you need Sentry in a non-prod environment temporarily, add that environment to both the `bundles.php` entry and the `when@<env>:` block in the config - never just one.

## 2. Configuration Shape - `dsn` / `messenger` / `tracing` vs `options`

This is the single most error-prone part of the bundle config. There are TWO levels of keys under `sentry:`:

| Level | Keys | What they are |
|---|---|---|
| **Top-level** (directly under `sentry:`) | `dsn`, `messenger`, `tracing`, `register_error_listener`, `register_error_handler` | Bundle-specific (Symfony integration) settings |
| **Under `sentry: options:`** | `environment`, `release`, `sample_rate`, `traces_sample_rate`, `send_default_pii`, `before_send`, `before_send_transaction`, `before_breadcrumb`, `ignore_exceptions`, `ignore_transactions`, `error_types`, `integrations`, `tags`, ... | Core SDK options passed straight to `\Sentry\init()` |

Putting an SDK option (e.g. `ignore_exceptions`) at the top level, or a bundle option (e.g. `messenger`) under `options:`, fails at `cache:clear` with `Unrecognized option "..." under "sentry"`.

The stub's `config/packages/sentry.yaml`:

```yaml
when@prod:
    sentry:
        dsn: '%env(SENTRY_DSN)%'
        messenger:
            enabled: true            # flush queued events at the end of each message handling
            capture_soft_fails: true # also capture exceptions marked for retry
        options:
            environment: '%kernel.environment%'
            #release: '%env(SENTRY_RELEASE)%'
            ignore_exceptions:
                - Symfony\Component\Security\Core\Exception\AuthenticationException
                - Symfony\Component\Security\Core\Exception\AccessDeniedException
                - Symfony\Component\Routing\Exception\ResourceNotFoundException
                - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

- **`dsn`** is the only required option. The stub reads it from the `SENTRY_DSN` env var (declared empty in `.env-append`). An empty DSN disables transport, so a prod deploy that forgets to set it simply sends nothing rather than erroring.
- **`environment`** defaults to the Symfony kernel environment; the stub sets `%kernel.environment%` explicitly. (The value is never empty, so the older `%env(APP_ENV)%` workaround is unnecessary.)
- **`release`** is commented out by default. Set it (e.g. from a `SENTRY_RELEASE` build-time env var) when you want errors grouped by deploy - see section 5.

## 3. Filtering - `ignore_exceptions`, NOT `IgnoreErrorsIntegration`

**`IgnoreErrorsIntegration` was removed in `sentry-symfony` 5.0.** The UPGRADE-5.0 guide states verbatim: "The `IgnoreErrorsIntegration` integration was removed. Use the `ignore_exceptions` option instead." Any config (or `services:` block) that references `Sentry\Integration\IgnoreErrorsIntegration` is for the 4.x line and breaks on the 5.x bundle the stub installs.

Use the `ignore_exceptions` option under `sentry: options:` (a flat list of exception FQCNs):

```yaml
sentry:
    options:
        ignore_exceptions:
            - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

The option performs an `is_a()` check, so listing a **base class also ignores its subclasses** - e.g. `AuthenticationException` covers every concrete authentication exception. This is why the stub's four entries (auth, access-denied, routing 404, HTTP 404) suppress the routine "expected" exceptions without enumerating every subclass.

Other filtering knobs (also under `options:`):

- **`ignore_transactions`** - a list of transaction-name strings to drop from performance monitoring (e.g. `'GET /health'`).
- **`before_send`** / **`before_send_transaction`** - callbacks receiving the event (and a hint) that return the event to keep it or `null` to drop it. Use these when the decision is dynamic (data-dependent) rather than a static class / name match. Wire a callback as a service via `'%env()%'`-style indirection or a service id (see the `traces_sampler` pattern in section 4 for the service-callback shape).

## 4. Sampling - errors vs performance

Two independent rates, both under `sentry: options:`:

| Option | Controls | Default | Notes |
|---|---|---|---|
| `sample_rate` | Fraction of ERROR events sent (0.0-1.0) | `1.0` | `1.0` sends every error. Lower it only under heavy volume. |
| `traces_sample_rate` | Fraction of performance TRANSACTIONS sent (0.0-1.0) | unset (no transactions) | Performance monitoring is OFF until you set this. |

```yaml
sentry:
    options:
        sample_rate: 1.0          # keep all errors
        traces_sample_rate: 0.2   # sample 20% of transactions for performance
```

For context-aware transaction sampling, use `traces_sampler` (a callback) instead of the flat rate. In Symfony it is wired as a service factory:

```yaml
sentry:
    options:
        traces_sampler: 'sentry.callback.traces_sampler'

services:
    sentry.callback.traces_sampler:
        class: 'App\Service\Sentry'
        factory: ['@App\Service\Sentry', 'getTracesSampler']
```

```php
public function getTracesSampler(): callable
{
    return static function (\Sentry\Tracing\SamplingContext $context): float {
        if ($context->getParentSampled()) {
            return 1.0; // inherit an upstream distributed-trace decision
        }

        return 0.25;
    };
}
```

Performance tracing of DBAL / Twig / cache / HTTP-client is configured under the top-level `tracing:` key (`tracing.dbal.enabled`, `tracing.twig.enabled`, etc.), independent of `traces_sample_rate`.

## 5. Environments and Releases

- **Environment** (`options.environment`) groups events by deploy stage. The stub uses `%kernel.environment%` so events show up under `prod`. Filter by environment in the Sentry UI to separate prod from any staging instance that also reports.
- **Release** (`options.release`) groups events by code version, enabling regression detection and suspect-commit features. The SDK does NOT auto-detect a release; supply one explicitly, typically from a build-time env var:

  ```yaml
  sentry:
      options:
          release: '%env(SENTRY_RELEASE)%'   # e.g. "my-project@1.4.2", set during the build
  ```

  Set the same release value when uploading source maps / artifacts so stack traces resolve. Without a release, every deploy's errors are lumped together and "resolved in next release" does not work.

## 6. Enriching Events - tags, context, user, breadcrumbs

All four use the SDK scope API (`Sentry\State\Scope`). Use `\Sentry\configureScope()` to set values for the rest of the request, or `\Sentry\withScope()` to set them for a single captured event only.

**Tags** (indexed, searchable - keep values short, max 200 chars):

```php
\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
    $scope->setTag('tenant', $tenantId);
});
```

Tags can also be set globally in config under `options.tags`:

```yaml
sentry:
    options:
        tags:
            app_tier: '%env(APP_TIER)%'
```

**Context** (arbitrary structured data, NOT searchable - use tags when you need to search):

```php
\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
    $scope->setContext('order', ['id' => $orderId, 'total' => $total]);
});
```

Keep contexts small - Sentry rejects oversized payloads with HTTP 413; the SDK trims large blobs but do not rely on it. The legacy `extra` bag is deprecated in favor of structured contexts.

**User** - the bundle pulls the request IP only when `send_default_pii` is `true`; set the user explicitly when you want identity on the event:

```php
\Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
    $scope->setUser(['id' => (string) $user->getId(), 'email' => $user->getEmail()]);
});
```

`send_default_pii` defaults to `false`. Leave it `false` unless you have decided, with privacy review, to send IP / cookies / headers. When enabled, the `RequestIntegration` attaches IP address, cookies, and headers to events.

**Breadcrumbs** - a trail of events leading up to an error. The SDK records many automatically (HTTP requests, console, queries); add custom ones with:

```php
use Sentry\Breadcrumb;

\Sentry\addBreadcrumb(
    category: 'payment',
    message: 'Charge authorized',
    metadata: ['amount' => $amount],
    level: Breadcrumb::LEVEL_INFO,
    type: Breadcrumb::TYPE_DEFAULT,
);
```

The buffer size is bounded by `options.max_breadcrumbs` (default `100`); customize or drop individual breadcrumbs with the `options.before_breadcrumb` callback (return the breadcrumb to keep it, `null` to discard).

## 7. Monolog Integration

The bundle does **not** auto-register a Monolog handler - it must be wired manually in `monolog.yaml`. Register the Sentry handler as a service and attach it as a `type: service` handler:

```yaml
# config/packages/monolog.yaml (within when@prod)
services:
    Sentry\Monolog\Handler:
        arguments:
            $hub: '@Sentry\State\HubInterface'
            $level: !php/const Monolog\Level::Error

when@prod:
    monolog:
        handlers:
            sentry:
                type: service
                id: Sentry\Monolog\Handler
```

This sends log records at/above the configured level to Sentry as events. The stub's channel / per-exception logging conventions, the `main` handler exclusions, and the `framework.exceptions.<FQCN>.log_channel:` routing live in `[[monolog]]` - do not restate them here; the Sentry handler is just one more handler added under `when@prod` alongside the channels documented there.

Decide between the two capture paths and avoid double-reporting:

- The bundle's **error listener** (`register_error_listener`, on by default) already captures unhandled exceptions directly. This is the stub's default path.
- A **Monolog handler** captures anything logged at the chosen level, including errors logged but not thrown.

Running both can report the same error twice (once via the listener, once via the log record an error handler emits). If you add the Monolog handler for full log coverage, consider setting `register_error_listener: false` (top-level under `sentry:`) so exceptions are captured once, through Monolog, rather than via both paths.

## 8. Stub Specifics

- **DSN env var:** `.env-append` ships `SENTRY_DSN=` (empty). Set it per environment in `.env.local` on the prod host; never commit a real DSN.
- **`Utils::sentryGetPublicKey()`:** the stub's `src/Utils/Utils.php` extracts the public key from `SENTRY_DSN` (the `https://<public-key>@...` segment) and exposes it to Twig via the `sentryGetPublicKey` function (`src/Twig/TemplateExtension.php`). This is for client-side / loader use (e.g. a browser SDK snippet), independent of the server-side bundle config above. If you change the DSN env-var name, update that regex too.
- **Prod-only by design:** because the bundle is `['prod' => true]`, none of this config affects DEV / test runs - the integration compiles only in the prod container.

## 9. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Unrecognized option "ignore_exceptions" under "sentry"` | `ignore_exceptions` placed at the top level instead of under `options:` | Move it under `sentry: options:` (section 2). |
| `Unrecognized option "messenger" under "sentry.options"` | Bundle option (`messenger` / `tracing` / `register_error_listener`) nested under `options:` | Move it to the top level under `sentry:`. |
| `Class "Sentry\Integration\IgnoreErrorsIntegration" not found` / unrecognized integration | 4.x-era `IgnoreErrorsIntegration` config on the 5.x bundle | Remove the integration + its `services:` block; use `options.ignore_exceptions` (section 3). |
| No events arrive in Sentry | `SENTRY_DSN` empty, OR the bundle is not enabled for the current environment | Set `SENTRY_DSN`; confirm `bundles.php` enables `SentryBundle` for that environment (it is `prod`-only by default). |
| Expected 404 / auth errors flooding Sentry | The exception class is not in `ignore_exceptions` | Add the base class (the `is_a()` check covers subclasses). |
| Same error reported twice | Both the error listener and the Monolog handler capture it | Disable `register_error_listener` when routing everything through Monolog (section 7). |
| No performance transactions in Sentry | `traces_sample_rate` unset | Set `traces_sample_rate` under `options:` (section 4); it is off by default. |

## 10. Version Constraints

| Package | Required |
|---|---|
| `sentry/sentry-symfony` | `^5.10` (latest stable; PHP `^8.0`, Symfony `^8.0` - covers Symfony 8.1 / PHP 8.5). `IgnoreErrorsIntegration` removed in 5.0 - use `ignore_exceptions`. |
| `sentry/sentry` | transitive (the underlying PHP SDK installed by `sentry/sentry-symfony`) |

The bundle is registered `['prod' => true]` by `symfony_install_skeleton()` in `.docker/container/scripts/symfony.sh`; it is not gated on the API Platform flag.
