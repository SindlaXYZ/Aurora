# HTTP-less Symfony Applications

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-12 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-http-less-symfony-applications
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/dependency-injection/tree/8.1/Kernel
* https://github.com/symfony/dependency-injection/blob/8.1/Kernel/RequiredBundle.php

Symfony 8.1 splits the kernel/bundle infrastructure out of `HttpKernel` into `DependencyInjection`, enabling applications (console runners, Messenger workers, schedulers, daemons) to boot WITHOUT loading `symfony/http-kernel` and `symfony/http-foundation`. Lower memory, faster boot, smaller dependency tree.

## 1. What Changed in Symfony 8.1

- `FrameworkBundle` is **split into 2 lighter bundles**:
  - `Symfony\Component\DependencyInjection\Kernel\ServicesBundle` - event dispatcher, filesystem, clock, env-var processors (auto-loaded as a dependency where needed)
  - `Symfony\Component\Console\ConsoleBundle` - command registration, argument resolver, console error listener (manual, for CLI apps)
- New non-HTTP `KernelInterface` lives in `Symfony\Component\DependencyInjection\Kernel` - exposes ONLY container-related API (no `handle(Request)`, no HTTP methods)
- `HttpKernel\Kernel` and `HttpKernel\KernelInterface` continue to extend the new types - full **backward compatibility**, no migration forced
- New `#[RequiredBundle]` attribute lets a bundle declare prerequisite bundles (repeatable, with optional `ignoreOnInvalid` flag)

## 2. Does This Apply to the Dockraft Stub?

**Short answer: not directly.** The Dockraft Symfony stub is HTTP-FULL - it ships API Platform 4.3, runs behind nginx, serves REST endpoints on `/v1/`. The standard `HttpKernel\Kernel` + `FrameworkBundle` stack remains the right choice for it.

**However**, you may still benefit from 8.1's split in three contexts even inside the main app:

| Scenario in Dockraft stub | Benefit | Action |
|---|---|---|
| `bin/console` invocations (cache:clear, doctrine:migrations, fixtures:load) | Slightly faster CLI boot due to lighter dep graph | None - automatic |
| Messenger workers (`bin/console messenger:consume`) | Reduced per-worker memory; matters when running multiple consumers | None - automatic |
| `src/Schedule.php` recurring tasks | Faster scheduler boot | None - automatic |
| **Separate microservice/worker** spun off from main app | Build it HTTP-less from the start using `AbstractKernel + KernelTrait` | Manual - see §3 below |

A future Dockraft stub variant - e.g. `.docker/stubs/symfony-worker/v8.1/` for pure background workers - could ship an HTTP-less skeleton. That's out of scope of the current stub.

## 3. Creating an HTTP-less Kernel

For a brand-new microservice / worker / CLI tool that NEVER serves HTTP:

```php
// src/Kernel.php
namespace App;

use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;

class Kernel extends AbstractKernel
{
    use KernelTrait;
}
```

`KernelTrait` is the HTTP-less equivalent of `MicroKernelTrait`. Same conventions for loading `config/bundles.php`, `config/packages/*.yaml`, and `config/services.yaml`.

Minimal `config/bundles.php`:

```php
return [
    Symfony\Component\Console\ConsoleBundle::class => ['all' => true],
];
```

`ServicesBundle` auto-loads as a dependency of `ConsoleBundle`. No `FrameworkBundle`, no `HttpKernel`, no `HttpFoundation`.

Opt out of the log directory entirely (worker writes to stderr or its own logger):

```env
# .env
APP_LOG_DIR=false
```

`getLogDir()` is now **nullable**.

## 4. `#[RequiredBundle]` Attribute

A bundle can declare prerequisite bundles. The kernel resolves the chain recursively at boot:

```php
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

#[RequiredBundle(AcmeCoreBundle::class)]
#[RequiredBundle(AcmeUtilBundle::class, ignoreOnInvalid: true)]
class AcmeBlogBundle extends AbstractBundle
{
}
```

- **Repeatable** - declare one attribute per required bundle
- **Recursive** - if A requires B, and B requires C, both B and C are loaded when A is loaded
- **`ignoreOnInvalid: true`** - skip the dependency silently if the class isn't autoloadable (use for optional integrations)

Useful for shared bundles distributed across multiple apps (the Aurora bundle in the Dockraft stack could use this to declare its hard dependencies once instead of relying on each app's `bundles.php` to be correct).

## 5. Type-hinting the new `KernelInterface`

If a service needs a kernel handle but doesn't care about HTTP plumbing, type-hint the non-HTTP interface:

```php
use Symfony\Component\DependencyInjection\Kernel\KernelInterface;

class BundleInspector
{
    public function __construct(private KernelInterface $kernel)
    {
    }
}
```

In an HTTP-full app, the Symfony `HttpKernel\Kernel` satisfies this type-hint (it extends the new interface). The service stays portable between HTTP and HTTP-less apps.

## 6. Class Relocations (with BC aliases)

Several classes moved from `HttpKernel` to `DependencyInjection`:

| Old location | New location |
|---|---|
| `Symfony\Component\HttpKernel\Bundle\BundleInterface` | `Symfony\Component\DependencyInjection\Kernel\BundleInterface` |
| `Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass` | `Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass` |
| `Symfony\Component\HttpKernel\Config\FileLocator` | `Symfony\Component\DependencyInjection\Kernel\FileLocator` |

Old FQCNs continue to work via aliases - existing code doesn't need to change.

## 7. Performance Implications

For HTTP-less apps (not the Dockraft main app):

- **Boot time**: noticeably faster - many fewer classes to autoload during kernel construction
- **Memory**: ~30-50% smaller footprint per worker process (no `HttpFoundation`/`HttpKernel` static state)
- **Cold-start sensitive workloads** (Lambda-style scheduled tasks, per-job spawned workers): biggest win

For HTTP-full apps (Dockraft stub) the benefit is incidental - `bin/console` invocations are slightly faster because the new `MergeExtensionConfigurationPass` location moved to `DependencyInjection` and CLI no longer pulls a transitive HTTP class for compile-time work it never executed.

## 8. When NOT to Use HTTP-less

| Need | Use HTTP-less? |
|---|---|
| Serving HTTP/REST/GraphQL/WebSocket-upgrade requests | ❌ No - needs `HttpKernel` |
| API Platform resources | ❌ No - entire stack depends on HttpKernel |
| Twig-rendered web UI | ❌ No |
| Symfony Mailer over HTTP transport (Mailgun API, SendGrid API, etc.) | ⚠️ HttpClient yes; HttpKernel no - feasible but check transport |
| Pure CLI batch processor | ✅ Yes |
| Long-running Messenger worker | ✅ Yes (separate kernel from main app) |
| Scheduled job daemon | ✅ Yes |
| Microservice consuming a message queue, writing to DB | ✅ Yes |

## 9. Migration Notes (8.0 → 8.1)

**No migration forced** for existing apps. `HttpKernel\Kernel` continues to work and now transparently extends `AbstractKernel`.

Voluntary migration steps if you want to leverage the split for a worker process:

1. Identify a worker that uses `FrameworkBundle` only for its console + event dispatcher (no Twig, no Form, no HTTP routing).
2. Create a new Symfony project (or sub-directory) with `AbstractKernel + KernelTrait` (§3).
3. Move the relevant services + entities + messenger handlers into it.
4. Replace `FrameworkBundle` in `bundles.php` with `ConsoleBundle`.
5. Verify by running the worker - its boot time and memory should drop measurably.

## 10. Anti-patterns

- **Switching the Dockraft main API stub to HTTP-less** - it MUST serve HTTP (API Platform, controllers). The kernel split doesn't apply.
- **Removing `FrameworkBundle` from an HTTP-serving app expecting things to keep working** - `FrameworkBundle` provides routing, session, HTTP cache, profiler, validator config, etc. Don't.
- **Type-hinting `HttpKernel\KernelInterface` in code that should be portable** - use the new `DependencyInjection\Kernel\KernelInterface` so the service works in both HTTP-less and HTTP-full kernels.
- **Loading both `FrameworkBundle` AND `ServicesBundle`/`ConsoleBundle` simultaneously** - `FrameworkBundle` already provides those services; double-loading duplicates compiler passes. Pick one stack per app.
- **Using `#[RequiredBundle]` to enforce ordering between bundles** - it ensures presence, not order. Bundle order is determined by `bundles.php`.

## 11. Summary

- HTTP-less is an **opt-in architecture** for apps that don't serve HTTP - workers, CLI tools, microservices.
- The Dockraft Symfony stub is HTTP-full and stays so. No action required.
- The `#[RequiredBundle]` attribute and the new `KernelInterface` location are useful framework-wide (not just for HTTP-less apps) - adopt them when writing distributable bundles or portable services.
- Full backward compatibility - no breaking changes, no forced migration.
