# HttpClient - RFC 9111 Caching and 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-http-client-improvements
* https://symfony.com/doc/8.1/http_client.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://datatracker.ietf.org/doc/html/rfc9111

Symfony 7.4 reworked HTTP client caching: the legacy `HttpCache` dependency is gone, replaced by Cache-component-backed storage that follows RFC 9111 (the modern HTTP caching specification). This rule documents that caching baseline (unchanged on 8.1) plus the 8.1 additions - including one behavior change: `CachingHttpClient`'s default `maxTtl` is now bounded at 86400 seconds.

## 1. When to Cache an HttpClient

Enable caching when:

- The remote API returns `Cache-Control` / `ETag` / `Last-Modified` headers.
- The same resource is fetched repeatedly within the cache window.
- The data is shared across users (e.g. country list, exchange rates, vendor catalogs).

Skip caching when:

- Responses depend on the authenticated user (without per-user keying).
- The API is mutating (POST / PUT / DELETE).
- Latency matters more than freshness (the cache lookup adds overhead).

## 2. Configuration

### Step 1 - Tag-aware cache pool

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            http_client_cache:
                adapter: cache.adapter.redis_tag_aware   # or cache.adapter.filesystem in DEV
                tags: true
                default_lifetime: 86400
```

### Step 2 - Scoped HTTP client

```yaml
# config/packages/framework.yaml
framework:
    http_client:
        scoped_clients:
            third_party.client:
                base_uri: 'https://api.third-party.com/v1/'
                caching:
                    cache_pool: http_client_cache
                    shared: true        # use shared cache (multi-user). DEFAULT.
                    max_ttl: 3600       # cap TTLs at 1 h regardless of upstream headers
```

## 3. Usage

```php
public function __construct(private HttpClientInterface $thirdPartyClient) {}

public function getProducts(): array
{
    // First call: actual HTTP request, cached.
    // Subsequent calls within max_ttl: served from cache.
    return $this->thirdPartyClient->request('GET', 'products')->toArray();
}
```

The scoped client name (`thirdPartyClient`) follows the `<name>.client` -> `<camelCaseName>Client` autowire convention.

## 4. Caching Options

| Option | Default | When to change |
|---|---|---|
| `cache_pool` | required | Must reference a tag-aware pool. |
| `shared` | `true` | Set to `false` ONLY when the response varies per user (rare with caching). |
| `max_ttl` | **86400s (8.1+)** - see §6 | Lower it when the upstream sends absurdly long TTLs you don't want to honor; raise it deliberately when you genuinely want longer-lived items. |

## 5. Rules

1. **Always use a tag-aware pool.** RFC 9111 caching uses `Vary` headers and conditional revalidation - non-tag-aware adapters cannot invalidate selectively.
2. **Never cache authenticated GETs with `shared: true`.** Different users will see each other's data. Either set `shared: false`, or scope the cache key with a per-user header in the request.
3. **DEV uses the filesystem adapter; PROD uses Redis.** Filesystem is fine on a single container; Redis is required for multi-server (already true for application cache in this stub).
4. **Don't cache 5xx by accident.** RFC 9111 caches successful responses by default - verify if your code paths produce non-idempotent results on a 503 cached response, and tighten `Cache-Control: no-store` upstream where needed.
5. **Bust cache with tags.** When the upstream pushes a webhook saying "products changed", invalidate by tag: `$cache->invalidateTags(['http.third_party.products'])`. The client tags responses automatically with the URL host + path prefix.

## 6. Symfony 8.1 - Behavior Change: Default `maxTtl` Is Now 86400s

Before 8.1, `CachingHttpClient`'s `maxTtl` was unset by default - upstream cache directives alone decided item lifetime, so an upstream sending `Cache-Control: max-age=31536000` (or no expiry at all under heuristic freshness) could leave cache items effectively eternal. Symfony 8.1 changes the default `maxTtl` to **86400 seconds (1 day)** to prevent eternal cache items.

What this means on upgrade:

- A project relying on UNBOUNDED upstream TTLs through `CachingHttpClient` now silently caps every item at 1 day unless `max_ttl` is set explicitly higher. Items that used to live for weeks now expire daily.
- This is the one HttpClient change in this rule that alters runtime caching behavior. If a scoped client genuinely needs items to live longer than a day, set `max_ttl:` to the desired value explicitly - do not depend on "unset = forever" anymore.
- Most projects WANT a bound here; the new default is the safer behavior. Re-check cache-hit expectations for any long-lived upstream resource after upgrading.

## 7. Symfony 8.1 - Other Additions

All additive; opt-in per scoped client or per call.

### 7.1 Custom DNS Resolution

8.1 adds custom DNS resolution via a decorating HTTP client - resolve hostnames to specific IPs (split-horizon DNS, pinning a host to a known IP, testing against a staging IP without editing `/etc/hosts`) without changing the request URL. Wrap the base client in the DNS-resolving decorator and supply the host-to-IP mapping.

### 7.2 `$allowList` on `NoPrivateNetworkHttpClient`

`NoPrivateNetworkHttpClient` blocks requests to private / internal IP ranges (SSRF protection). 8.1 adds an `$allowList` constructor argument so specific otherwise-blocked hosts / ranges can be permitted - e.g. one trusted internal service that must be reachable while every other private address stays blocked.

```php
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

// Block private networks, but explicitly allow one internal host.
$client = new NoPrivateNetworkHttpClient($inner, allowList: ['10.0.5.10']);
```

Keep the allow-list as tight as possible - each entry is a hole in the SSRF guard.

### 7.3 `max_connect_duration` Option

A new per-request / per-client `max_connect_duration` option caps the time spent establishing the connection (DNS + TCP + TLS handshake), independent of `timeout` (idle/inactivity) and `max_duration` (total transfer). Use it to fail fast on a host that accepts the socket slowly without waiting out the full `max_duration`.

```php
$response = $client->request('GET', $url, [
    'max_connect_duration' => 2.0,   // fail if the connection isn't established within 2s
    'timeout'              => 10,
    'max_duration'         => 30,
]);
```

### 7.4 Persistent cURL Handles

8.1 adds support for persistent cURL handles - the underlying cURL handle is reused across requests rather than recreated, reducing connection-setup overhead for a client that issues many requests to the same host (a hot scraping or polling loop). This is a performance optimization with no API change at the call site.

### 7.5 `GuzzleHttpHandler`

8.1 ships a `GuzzleHttpHandler` that lets Symfony HttpClient be used as a Guzzle handler. Use it when a third-party SDK is hard-wired to Guzzle but you want all its outbound traffic to go through Symfony's HttpClient (so it inherits scoped-client config, the profiler, retry, caching, and `NoPrivateNetworkHttpClient` protection) instead of Guzzle's own transport.

### 7.6 Stale-If-Error Fallback Logging

`CachingHttpClient` now logs when it serves a stale cached response because the upstream errored (the RFC 9111 `stale-if-error` fallback). Previously this fallback was silent; the log line makes it visible that a response was served stale due to an upstream failure - watch for it when diagnosing "why is this data old?" reports.

## 8. Common Errors

| Error | Cause | Fix |
|---|---|---|
| Cache hit rate is 0 | Pool not tag-aware OR upstream sends `Cache-Control: no-store` | Verify pool adapter is `*_tag_aware`; inspect `X-Cache-Control` in the actual response. |
| Items expire after 1 day when you expected longer | 8.1 default `maxTtl` is now 86400s (§6) | Set `max_ttl:` explicitly to the desired lifetime on the scoped client. |
| Stale data after upstream change | Upstream TTL too long; no webhook-driven invalidation | Set `max_ttl:` lower OR add tag-based invalidation. |
| `LogicException: A cache pool is required` | Caching enabled but `cache_pool:` missing | Provide a pool in `scoped_clients.<name>.caching.cache_pool`. |
| A trusted internal host is blocked as private | `NoPrivateNetworkHttpClient` with no allow-list | Add the host to `$allowList` (§7.2) - keep the entry as narrow as possible. |

## 9. Version Constraints

| Package | Required |
|---|---|
| `symfony/http-client` | `^7.4` (for RFC 9111 caching via the cache component), `^8.1` (for the bounded default `maxTtl` of 86400s, custom DNS resolution, `$allowList` on `NoPrivateNetworkHttpClient`, `max_connect_duration`, persistent cURL handles, `GuzzleHttpHandler`, and stale-if-error fallback logging) |
| `symfony/cache` | `^7.0` (tag-aware adapter) |
| `predis/predis` or `ext-redis` | required when using `cache.adapter.redis_tag_aware` |
