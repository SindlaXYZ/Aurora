# proxy-cheap.com - Rotating Residential Proxies

| Version | Created    | Updated    |
|---------|------------|------------|
| 2.2     | 2026-05-21 | 2026-05-29 |

**Sources:**
* https://www.proxy-cheap.com/
* https://www.proxy-cheap.com/services/rotating-residential-proxies
* https://docs.proxy-cheap.com/
* https://github.com/proxy-cheap/proxycheap-node
* https://github.com/proxy-cheap/proxycheap-node/blob/master/API.md
* https://github.com/proxy-cheap/proxycheap-node/blob/master/src/client.js
* https://www.npmjs.com/package/proxycheap.js
* https://support.proxy-cheap.com/hc/en-us/articles/24651487956381-How-to-use-residential-rotating-proxies
* https://support.proxy-cheap.com/hc/en-us/articles/30423207064349-How-to-use-residential-rotating-proxies-Step-by-Step-Tutorial

`proxy-cheap.com` is a proxy provider with several product lines. This rule focuses on the **Rotating Residential** service (80 million-IP pool across 180+ countries, $4.99/GB at the time of writing) because that is the one the stub-level Symfony code typically integrates with for scraping and third-party-request workflows (see `.claude/rules/symfony.md` and the `ThirdPartyRequestEntityService` pattern under `src/Service/Entity/`).

The provider exposes TWO distinct HTTP surfaces. Failing to keep them apart is the most common source of "why does my code not work" tickets - they answer different questions, use different hostnames, and use different authentication mechanisms.

| Surface              | Hostname                       | What you do here                                                | Auth                        |
|----------------------|--------------------------------|------------------------------------------------------------------|-----------------------------|
| REST Management API  | `https://api.proxy-cheap.com`  | Buy, list, configure, top-up bandwidth, whitelist IPs            | `X-Api-Key` + `X-Api-Secret` headers |
| Proxy Gateway        | gateway shown in your dashboard | USE the rotating residential pool (route HTTP/SOCKS5 traffic)    | Proxy-protocol `user:password` |

The dockraft stub SHIPS a reference integration for both surfaces - this rule is the spec for how it works and how to extend it:

| File                                                       | Role                                                                 |
|------------------------------------------------------------|----------------------------------------------------------------------|
| `config/packages/proxy_cheap.yaml`                         | Scoped HTTP client `proxy_cheap.client` + empty env-var defaults     |
| `src/Service/ThirdParty/ProxyCheapClient.php`              | REST management API wrapper (balance / list / quote / order)         |
| `src/Service/ThirdParty/ProxyCheapGateway.php`             | DSN builder for the rotating-residential gateway                     |
| `src/Command/ProxyCheapCommand.php`                        | Multi-action demo: `app:proxy-cheap --action=<balance\|listProxies\|quote\|order\|scrape>` |

The integration is `always-loaded-but-not-configured`: the scoped client and services compile into every container, but the env vars default to empty strings, so no HTTP traffic happens until the project populates `.env.local` and invokes the command. This matches the stub's opt-in pattern without forcing per-project boilerplate.

## 1. REST Management API - `https://api.proxy-cheap.com`

Source for everything below: the official `proxycheap.js` Node SDK at `src/client.js` (the proxy-cheap own SDK is the most authoritative public source for endpoint paths; the docs.proxy-cheap.com pages are JS-rendered and hard to scrape programmatically).

### 1.1 Authentication

Two custom headers MUST be sent on every request - there is no Bearer / Basic / OAuth flow:

```
X-Api-Key:    <from dashboard>
X-Api-Secret: <from dashboard>
User-Agent:   <your project name>/<version>
```

Generate the pair at https://app.proxy-cheap.com under API keys. Both halves are required - sending only the key returns 401. Treat both as secrets (NEVER commit them; see `.env-append` pattern below).

### 1.2 Endpoints

Every URL is `https://api.proxy-cheap.com/<path>`. The 8 paths used by the official SDK:

| Method | Path                                          | Purpose                                  | SDK method                       |
|--------|-----------------------------------------------|------------------------------------------|----------------------------------|
| GET    | `account/balance`                             | Account balance (USD)                    | `balance()`                      |
| GET    | `proxies`                                     | List all proxies on the account          | `proxies()`                      |
| GET    | `proxies/{id}`                                | One proxy's details                      | `proxy(id)`                      |
| GET    | `proxies/{id}/whitelist-ip`                   | Update whitelisted IPs (with body)       | `whitelist(id, [ips])`           |
| GET    | `proxies/{id}/extend-period`                  | Extend duration                          | `extend(id, months)`             |
| GET    | `proxies/{id}/buy-bandwidth`                  | Top up bandwidth                         | `buyBandwidth(id, amountGb)`     |
| POST   | `order/configuration`                         | Get a price quote for an order config    | `configuration(body)`            |
| POST   | `order/execute`                               | Place an order (charges the balance)     | `order(body)`                    |
| POST   | `proxies/{id}/auto-extend/enable` or `/disable` | Toggle auto-renew on a proxy           | `autoExtend(id, enabled)`        |

Note: the SDK uses `GET` for several endpoints that semantically mutate state (`whitelist-ip`, `extend-period`, `buy-bandwidth`). Mirror what the upstream does - do NOT "improve" them to `PUT/POST` in your client; the API rejects the unexpected verb.

### 1.3 `order/configuration` and `order/execute` body parameters

The same body shape is accepted by both endpoints. `configuration` returns a price quote without charging; `execute` actually places the order. ALWAYS call `configuration` first in code paths a developer might run by mistake.

| Field                  | Type              | Notes                                                                 |
|------------------------|-------------------|-----------------------------------------------------------------------|
| `networkType`          | string enum       | `MOBILE`, `DATACENTER`, `RESIDENTIAL`, `RESIDENTIAL_STATIC`. For rotating residential use `RESIDENTIAL`. |
| `ipVersion`            | string enum       | `IPv4`, `IPv6`, `MOBILE`                                              |
| `country`              | string (ISO 3166-1 alpha-2) | e.g. `US`, `RO`, `DE`                                       |
| `region`               | string            | 2-letter region/state code where supported                             |
| `isp`                  | int               | Specific ISP id (advanced; usually omit)                              |
| `proxyProtocol`        | string enum       | `HTTP`, `HTTPS`, `SOCKS5`                                              |
| `authenticationType`   | string enum       | `USERNAME_PASSWORD` or `IP_WHITELIST`                                  |
| `ipWhitelist`          | string[]          | Required when `authenticationType=IP_WHITELIST`                        |
| `package`              | int               | Bandwidth / plan id from the dashboard                                 |
| `quantity`             | int               | Number of proxies to buy                                               |
| `couponCode`           | string            | Optional                                                               |
| `bandwidth`            | int (GB)          | For bandwidth-priced products (rotating residential is per-GB)         |
| `isAutoExtendEnabled`  | bool              | Renew automatically when bandwidth depletes                            |
| `autoExtendBandwidth`  | int (GB)          | GB to add on each auto-extend cycle                                    |

## 2. Symfony Integration - REST API Client

The stub ships this as `src/Service/ThirdParty/ProxyCheapClient.php`, wired through the scoped HTTP client pattern from `.claude/rules/http-client.md`. The reference code snippets in §2.1-§2.2 below MATCH the files in the stub - when one is edited, the other MUST be updated to match. The stub's `ThirdPartyRequestEntityService` (`src/Service/Entity/`) is the recommended production wrapper around the client; it persists every API hit (replayable for debugging) and inherits the `TransportException|ServerException` handling that protects callers from upstream timeouts.

### 2.1 Configuration

Shipped as `config/packages/proxy_cheap.yaml` (loaded unconditionally; env vars default to empty strings via `parameters: env(PROXY_CHEAP_*): ''` so the container compiles with no proxy-cheap setup):

```yaml
# config/packages/proxy_cheap.yaml
framework:
    http_client:
        scoped_clients:
            proxy_cheap.client:
                base_uri: 'https://api.proxy-cheap.com/'
                headers:
                    X-Api-Key:    '%env(PROXY_CHEAP_API_KEY)%'
                    X-Api-Secret: '%env(PROXY_CHEAP_API_SECRET)%'
                    User-Agent:   'dockraft-proxy-cheap-client/1.0 (+https://__DOMAIN__)'
                    Accept:       'application/json'
                # The management API responses are tiny (KB-scale) - no caching needed.
                # Adding caching here can mask charge / balance changes.
                timeout:      10
                max_duration: 30
```

```dotenv
# .env.local (NEVER commit)
###> proxy-cheap.com management API ###
# https://app.proxy-cheap.com -> API keys
PROXY_CHEAP_API_KEY=
PROXY_CHEAP_API_SECRET=
###< proxy-cheap.com management API ###
```

### 2.2 Reference client class

```php
namespace App\Service\ThirdParty;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

final readonly class ProxyCheapClient
{
    public function __construct(
        // Autowired by parameter name from scoped_clients.proxy_cheap.client
        private HttpClientInterface $proxyCheapClient,
    ) {}

    /**
     * Account balance in USD.
     *
     * @throws HttpClientException on network failure or non-2xx status
     */
    public function getBalance(): float
    {
        $body = $this->proxyCheapClient->request('GET', 'account/balance')->toArray();

        return (float) ($body['balance'] ?? 0.0);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws HttpClientException
     */
    public function listProxies(): array
    {
        return $this->proxyCheapClient->request('GET', 'proxies')->toArray();
    }

    /**
     * Get a price quote for a rotating-residential order WITHOUT charging the account.
     * Always call this before order() in any code path that could be run by mistake.
     *
     * @return array<string, mixed> The quote payload (price, currency, etc.)
     * @throws HttpClientException
     */
    public function quoteRotatingResidential(string $countryCode, int $bandwidthGb): array
    {
        return $this->proxyCheapClient->request('POST', 'order/configuration', [
            'json' => [
                'networkType'        => 'RESIDENTIAL',
                'proxyProtocol'      => 'HTTP',
                'authenticationType' => 'USERNAME_PASSWORD',
                'country'            => strtoupper($countryCode),
                'bandwidth'          => $bandwidthGb,
                'quantity'           => 1,
            ],
        ])->toArray();
    }

    /**
     * Place a real order. CHARGES the account balance.
     *
     * @return array<string, mixed> The order confirmation (proxy id, credentials, etc.)
     * @throws HttpClientException
     */
    public function orderRotatingResidential(string $countryCode, int $bandwidthGb): array
    {
        return $this->proxyCheapClient->request('POST', 'order/execute', [
            'json' => [
                'networkType'        => 'RESIDENTIAL',
                'proxyProtocol'      => 'HTTP',
                'authenticationType' => 'USERNAME_PASSWORD',
                'country'            => strtoupper($countryCode),
                'bandwidth'          => $bandwidthGb,
                'quantity'           => 1,
            ],
        ])->toArray();
    }
}
```

### 2.3 Routing through `ThirdPartyRequestEntityService`

For projects that use the stub's `ThirdPartyRequest` audit table (every external call persisted with response body + status), wrap the call so it goes through `ThirdPartyRequestEntityService::createThirdPartyRequest()` instead of `HttpClientInterface` directly. The `responseBody` column then holds the full API response for hours-later debugging, and the cron-driven `ThirdPartyRequestCommand::markStaleAsNotExecuted()` (see `src/Command/Entity/ThirdPartyRequestCommand.php` + `src/Repository/ThirdPartyRequestRepository.php`) recovers any row that somehow got stuck. The `TransportExceptionInterface | ServerExceptionInterface` handler inside `ThirdPartyRequestEntityService::call()` (terminate-as-timed-out at the source) protects this client specifically from leaking exceptions on a slow / 504-returning Proxy-Cheap endpoint, which matters more here than for direct first-party calls because rotating residential adds 5-15s of latency to every request.

## 3. USING the Rotating Residential Proxies - Gateway, NOT the REST API

This is the second HTTP surface and the more commonly confused one. The REST API does NOT route your scraping traffic. The dashboard issues you **gateway credentials** (a hostname + port + user/password) that you use as a PROXY in any HTTP client.

### 3.1 Ports and protocols

| Protocol | Port | Use case                                |
|----------|------|-----------------------------------------|
| HTTP     | 5959 | Default for most clients (curl, Symfony HttpClient, Guzzle) |
| SOCKS5   | 9595 | When the target requires raw TCP / non-HTTP, OR for headless browsers via SOCKS |

The gateway hostname itself is shown in the dashboard (Credentials Generator) when you create credentials. Treat it as an opaque value - copy it verbatim into `.env.local`, do NOT hard-code it in source.

### 3.2 Username format (rotation control)

The dashboard generates a username string that encodes targeting + rotation policy. The exact syntax varies between provider plan generations and is documented per account in the dashboard; the high-level pattern is:

- Plain username -> Random IP rotation (every request gets a new exit IP)
- Username with a `session-<id>` suffix -> Sticky session (the same exit IP is held for the session, typically ~30 minutes before forced rotation)
- Targeting parameters (country, city/region, ISP) are encoded as additional dash-separated tokens inside the username (e.g. country-US, region-NY)

Do NOT invent the syntax in code - copy it verbatim from the dashboard. When sticky sessions are needed for a workflow (e.g. multi-step form submission), generate the `session-<id>` value PER WORKFLOW INSTANCE (e.g. a UUID per scraping job), not once per process.

### 3.3 Wiring the gateway into Symfony HttpClient

```yaml
# config/packages/framework.yaml
framework:
    http_client:
        scoped_clients:
            # The scraper client: every request goes through Proxy-Cheap rotating residential.
            scraper.client:
                base_uri: 'https://target-site.example.com/'
                # The proxy DSN: http://<username>:<password>@<gateway-host>:<port>
                proxy:    '%env(PROXY_CHEAP_GATEWAY_DSN)%'
                # Targets often serve different content over HTTPS - keep the cert check ON.
                verify_peer: true
                verify_host: true
                # Rotating residential is significantly slower than datacenter; raise timeouts.
                timeout:         30
                max_duration:    60
```

```dotenv
# .env.local (NEVER commit)
###> proxy-cheap.com gateway (rotating residential) ###
# Copy the literal DSN from the dashboard Credentials Generator.
# Example shape: http://USER-country-US-session-RAND:PASS@<gateway-host>:5959
PROXY_CHEAP_GATEWAY_DSN=
###< proxy-cheap.com gateway (rotating residential) ###
```

### 3.4 Programmatic per-request proxy override

When different code paths need different exit countries or session policies, override `proxy` per request rather than declaring N scoped clients. The stub ships `App\Service\ThirdParty\ProxyCheapGateway` as the canonical DSN builder so callers do not assemble the URL by hand (which often leaks passwords into logs or skips `rawurlencode` on special characters):

```php
use App\Service\ThirdParty\ProxyCheapGateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeoScraper
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ProxyCheapGateway $proxyCheapGateway,
    ) {}

    public function scrapeFromCountry(string $url, string $countryCode, ?string $sessionId = null): string
    {
        // null sessionId = random rotation; non-null = sticky session (~30 min hold).
        $dsn = $this->proxyCheapGateway->buildHttpDsn($countryCode, $sessionId);

        return $this->httpClient->request('GET', $url, [
            'proxy'        => $dsn,
            'timeout'      => 30,
            'max_duration' => 60,
        ])->getContent();
    }

    public function scrapeStickyPerJob(string $url, string $countryCode): string
    {
        // Fresh session id per scraping job - the dashboard holds the same exit IP for ~30 min,
        // then forces a rotation. See rule §3.2 - keeping a session across many jobs increases
        // ban rates AND defeats the rotation pool.
        $sessionId = $this->proxyCheapGateway->newSessionId();

        return $this->scrapeFromCountry($url, $countryCode, $sessionId);
    }
}
```

## 4. Rules

1. **Two surfaces, two secrets.** The `X-Api-Key` / `X-Api-Secret` pair (management API) is NOT the same as the proxy gateway username/password (usage). Both live in `.env.local`; using the wrong one returns 401 from the API or `407 Proxy Authentication Required` from the gateway.
2. **`configuration` before `execute`.** Calling `order/execute` charges the account immediately and is NOT reversible from the API. ALWAYS run `order/configuration` first in code reviews, tests, and any CLI command that could be invoked by mistake. The reference `ProxyCheapClient` above puts them in two separate methods on purpose.
3. **Rotate session ids per workflow.** Sticky `session-<id>` values held for the entire process lifetime defeat the rotation pool (you keep landing on the same exit) AND increase ban rates on the target. Generate one session id per logical workflow (one form submission, one product page traversal) and let it expire naturally.
4. **NEVER commit credentials.** Both pairs live in `.env.local` (gitignored). Audit `.env.dist` / `.env` for any leaks before each release. If a key DOES leak, rotate in the dashboard immediately - leaked keys can drain the account balance via `order/execute`.
5. **Wrap third-party HTTP through `ThirdPartyRequestEntityService`** (see `src/Service/Entity/` in the stub). The audit table makes "why did this scrape miss data?" answerable hours later. The `TransportException|ServerException` handling added in the service (terminal `STATUS_TIMED_OUT`) is critical for proxy-routed requests because rotating residential is slower and more failure-prone than direct calls.
6. **Test in DEV with a few-GB plan, not the production account.** Proxy-Cheap supports separate API keys per account - create a dedicated test account, top it with $5-10, and point CI/DEV at it. Production keys never leave `.env.local` on the deployment server.
7. **Respect provider rate limits per endpoint.** The REST API does not publish per-endpoint quotas but rapid-fire `order/execute` calls are flagged as fraud. Throttle order endpoints behind `symfony/rate-limiter` (`.claude/rules/rate-limiter.md` in the v8.0 stub) when the project exposes them to end users.
8. **The gateway is HTTP/HTTPS-aware, not HTTP/2-aware.** Symfony HttpClient defaults to HTTP/1.1 over proxies, which is correct here. Forcing HTTP/2 via `http_version: '2.0'` while a proxy is configured can cause the connection to silently downgrade or fail; leave it unset.

## 5. Inspiration: the official Node SDK

The proxy-cheap-maintained Node SDK (`proxycheap.js` v2.2.0 on npm) at https://github.com/proxy-cheap/proxycheap-node is the de-facto reference for the management API. There is no first-party PHP SDK. The PHP integration in this stub is a deliberate thin wrapper around Symfony HttpClient rather than a port of the Node SDK - the SDK is referenced for endpoint paths, parameter names, and error shapes ONLY:

```javascript
// src/client.js (Node SDK) - what dockraft uses as the source of truth for endpoint paths
this.API_URL = "https://api.proxy-cheap.com";

// Headers
"X-Api-Key":    this.API_KEY,
"X-Api-Secret": this.API_SECRET,
"User-Agent":   `proxycheap.js ${pkg.version} (https://github.com/LockBlock-dev/proxycheap.js)`,

// URL template
url: `${this.API_URL}/${path}`,
```

When upstream changes a path or parameter, the SDK is updated first (within hours, historically) and the docs.proxy-cheap.com pages follow within days. Track the SDK's `CHANGELOG.md` and bump this rule's `Updated` date when paths or auth headers change.

## 6. Common Errors

| Symptom                                                   | Cause                                                          | Fix                                                                 |
|-----------------------------------------------------------|----------------------------------------------------------------|---------------------------------------------------------------------|
| HTTP 401 on every management API call                     | Missing or wrong `X-Api-Key` / `X-Api-Secret`, or sending only one of the two | Both headers MUST be present; verify env vars are loaded (`bin/console debug:dotenv`). |
| HTTP 402 / `Insufficient balance` on `order/execute`      | Account out of credit                                          | Top up via the dashboard; run `getBalance()` defensively before order. |
| HTTP 422 / `Invalid configuration` on `order/execute`     | Field enum value misspelled (e.g. `Residential` instead of `RESIDENTIAL`) | The enums are CASE-SENSITIVE; copy them from §1.3 verbatim. |
| `407 Proxy Authentication Required` from the gateway      | Wrong proxy DSN credentials, OR using the API key/secret instead of gateway user/password | The gateway uses a SEPARATE credential pair issued in the dashboard. |
| Target site returns CAPTCHAs even with rotating residential | Sticky session id held too long (target fingerprinted the IP) | Rotate `session-<id>` per workflow; consider switching to random rotation. |
| `Connection refused` to gateway port                      | Wrong port for the protocol                                    | HTTP -> 5959, SOCKS5 -> 9595. Do not invert. |
| Bandwidth bill higher than expected                       | Each retry through the proxy counts; failed scrapes still consume bytes | Cap retries in `ThirdPartyRequestEntityService` (already in stub); cache GET responses where possible. |
| Symfony HttpClient hangs forever on first proxy request   | No `timeout` set; rotating residential can take 5-15s to establish the upstream connection | Set `timeout: 30` and `max_duration: 60` per scoped_client or per request. |

## 7. Version Constraints

This rule has no `composer require` line because Proxy-Cheap is a SERVICE, not a library. The integration depends on Symfony HttpClient (already in the stub via `symfony/http-client`) and standard env-var handling.

| Component                                  | Version          |
|--------------------------------------------|------------------|
| `proxycheap.js` (Node SDK - reference only) | `^2.2`           |
| Proxy-Cheap REST API                       | unversioned (no `/v1/` prefix); track the Node SDK CHANGELOG for breaking changes |
| `symfony/http-client`                      | `^8.1` (stub default) |
