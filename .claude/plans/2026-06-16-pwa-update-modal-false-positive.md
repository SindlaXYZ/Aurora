---
status: done
created: 2026-06-16
implemented: 2026-06-16
---

# PWA "new version available" modal fires on almost every revisit

> **Implemented 2026-06-16.** The §9 open question ("is the per-session SW version
> intentional?") was resolved by the user's directive to implement §8.1 as written: the
> session suffix was **removed** (no runtime per-user namespacing was required). Changes:
> - `AuroraPWA::version()` rewritten — git hash + a deploy-stable suffix only; the session
>   suffix and the `!php/eval` path are gone (new private `versionAppend()` /
>   `sanitizeVersionToken()`; the dead session property + constructor block + the unused
>   `RequestStack` dependency were removed).
> - `aurora.yaml` schema: `version_append` defaults to `''`; the `!php/eval`/`date()` footgun
>   removed and replaced with a documented stable-value contract.
> - `pwa-sw.js.twig`: no eager `skipWaiting()` on install (B1); hardened fetch caching +
>   guaranteed non-`undefined` fallback (B5).
> - `pwa-main.js.twig`: real `controllerchange` reload-once listener (B3); surface an
>   already-waiting worker (B4); working Reload button (B2); dead `{% if false %}` block + dead
>   `registration.controllerchange` removed (B6); install-button label typo fixed.
> - Regression tests added in `tests/Utils/AuroraPWA/PWAMainJsTest.php` (version is git-hash-only
>   despite a set session, plain suffix passthrough, sanitization, legacy `!php/eval` ignored).
>   PWA suite: 9/9 green.

> Investigation + fix plan for the `#update-modal` ("A new version of the application is
> available. Reload to update.") that pops up on nearly every return visit on a production
> host application, even though no new application version was deployed.
>
> Files in scope:
> - `src/templates/pwa-main.js.twig` (client registration + `showUpdateNotification`)
> - `src/templates/pwa-sw.js.twig` (the service worker)
> - `src/Utils/AuroraPWA/AuroraPWA.php` (`version()`, `serviceWorkerJS()`, `mainJS()`)
> - `src/Resources/schema/packages/aurora.yaml` (`aurora.pwa.version_append` default)

## 1. Context

Aurora ships a self-contained PWA layer: `AuroraPWA::serviceWorkerJS()` renders
`pwa-sw.js.twig` and `AuroraPWA::mainJS()` renders `pwa-main.js.twig`. The browser
registers the service worker (SW) on every page load; when the SW *script bytes* differ
from the installed copy the browser raises `updatefound`, and `pwa-main.js.twig`'s
`onupdatefound` handler eventually calls `showUpdateNotification()` which builds the
`#update-modal`.

Both `serviceWorkerJS()` and `mainJS()` are rendered fresh on every request (no
server-side cache wrapper) and are sent with `no-cache, no-store, must-revalidate,
max-age=0`. That is the *correct* policy for a SW script — but it means the browser
re-fetches and byte-compares the SW on every `register()` call, so **any** difference in
the rendered bytes is immediately seen as "a new service worker".

## 2. Problem

On the production host app the `#update-modal` appears on almost every re-entry, with no
real deploy in between. A genuine "update available" prompt should appear **once**, right
after an actual code deploy — not on routine revisits.

## 3. Root cause

The SW cache names embed the PWA version:

```twig
{# src/templates/pwa-sw.js.twig:1-2 #}
var PRECACHE = 'precache-{{ pwaVersion }}';
var RUNTIME  = 'runtime-{{ pwaVersion }}';
```

`pwaVersion` comes from `AuroraPWA::version()` (`src/Utils/AuroraPWA/AuroraPWA.php:313`),
which mixes **volatile, per-session / time-based data** into the version string. When that
data changes, the SW bytes change, and the browser fires `updatefound` → the modal — with
no deploy involved.

Two independent contributors, both feeding the same failure:

### 3a. Session attribute appended to the version — PRIMARY suspect

`src/Utils/AuroraPWA/AuroraPWA.php:331-336`:

```php
if ($cookieSessionId && $this->session instanceof SessionInterface) {
    $sessionIdentifier = $this->session->get('PHPSESSID');
    if (null !== $sessionIdentifier && '' !== (string)$sessionIdentifier) {
        $version .= '_' . (string)$sessionIdentifier;   // <-- per-session suffix in the SW version
    }
}
```

When the `PHPSESSID` cookie is present **and** the app stores a `PHPSESSID` *session
attribute* (the production app does — the test fixture `tests/Utils/AuroraPWA/PWAMainJsTest.php:291`
sets exactly this), the SW version becomes `…_<sessionValue>`. That value changes whenever
the session changes:

- session GC / idle expiry (a user returning the next day);
- Symfony session-id migration on login / logout / privilege change;
- any host-side rotation of the stored `PHPSESSID` attribute.

Every such change → new `PRECACHE`/`RUNTIME` names → new SW bytes → `updatefound` → modal.
This matches "almost every re-entry": users naturally come back with rotated/expired
sessions.

Putting per-user/session data into the SW script is architecturally wrong: the SW is a
**single, origin-global** resource. Its version must track the *application/deploy*, never
the visitor's session.

### 3b. `version_append` evaluated with `!php/eval` — SECONDARY suspect

`src/Utils/AuroraPWA/AuroraPWA.php:338-343`:

```php
if (0 === strpos($versionAppend, '!php/eval')) {
    preg_match('/`(.*)`/', $versionAppend, $match);
    if (isset($match[1]) && !empty($match[1])) {
        $version .= '_' . substr(sha1(eval("return " . trim($match[1], ';') . ";")), 0, 15);
    }
}
```

The default config and its comment (`src/Resources/schema/packages/aurora.yaml:16-17`):

```yaml
#aurora.pwa.version_append:        "!php/eval `date('Y-m-d H')`"
aurora.pwa.version_append: "!php/eval `App\Utils::pwaVersionAppend()`"
```

- The commented example `date('Y-m-d H')` changes **every hour for every visitor** — on its
  own that produces an hourly false update for the entire audience. The schema effectively
  advertises a footgun.
- The active default calls the host-owned `App\Utils::pwaVersionAppend()`. If that returns
  anything time-based or per-request (a timestamp, `microtime`, a per-request token), the SW
  churns the same way. **This is the single most likely live trigger and must be checked
  first** (see §4).
- `eval()` of a config-supplied string is also a security and maintainability problem
  independent of the churn (arbitrary code execution surface, opaque to static analysis).

### 3c. Why the byte-compare always runs

`mainJS()` (`AuroraPWA.php:206`) and `serviceWorkerJS()` (`AuroraPWA.php:279`) are not
server-cached and send `no-store`; `pwa-main.js.twig:33` calls `register()` on every load.
So the SW is re-rendered and re-fetched every visit and `version()` is re-evaluated every
visit — there is no debounce that could mask 3a/3b.

## 4. Confirm the diagnosis first (cheap, decisive)

Before changing code, confirm which contributor is live on the host app:

```bash
# Fetch the SW twice in the SAME session and diff. The PRECACHE/RUNTIME lines reveal the
# volatile suffix. (Run against the host app, not committed anywhere.)
curl -s --cookie "PHPSESSID=<one-stable-session>" https://<host>/<sw-path> | head -3
# ...wait, repeat, diff. A changing 'precache-<hash>_<X>' pin is the smoking gun.
```

Also have the host-app owner print `App\Utils::pwaVersionAppend()` and confirm it is
**stable across a deploy** (not time-based). In DevTools → Application → Service Workers,
"updatefound"/repeated "installed" on a plain reload confirms script churn.

Expected outcome: the trailing `_<…>` segment of the cache names differs between two loads —
identifying 3a (session) and/or 3b (`version_append`).

## 5. Secondary bugs (real, fix alongside — they break even a *legitimate* update)

These do not cause the false positive, but they make the update flow itself broken, so a
real update behaves poorly too.

- **B1 — `skipWaiting()` invoked eagerly / during install.**
  `src/templates/pwa-sw.js.twig:25`:
  ```js
  .then(self.skipWaiting())   // calls skipWaiting() now, passes its Promise to .then()
  ```
  Should be `.then(() => self.skipWaiting())` — *or* not skip-on-install at all (see §7).
  Effect: the new SW leaves the waiting phase during install, so by the time
  `pwa-main.js.twig` reaches the `installed` state and shows the modal, `registration.waiting`
  is frequently already `null`. The modal's Reload button then does nothing useful (see B2).

- **B2 — Reload button's `postMessage` is usually a no-op.**
  `src/templates/pwa-main.js.twig:196-198`:
  ```js
  if (registration.waiting) {
      registration.waiting.postMessage({type: 'SKIP_WAITING'});
  }
  ```
  Because of B1, `registration.waiting` is typically `null` here, so clicking "Reload" just
  removes the modal overlay without activating any new worker.

- **B3 — Dead `controllerchange` handler → no auto-reload after update.**
  `src/templates/pwa-main.js.twig:91-93`:
  ```js
  registration.controllerchange = function () { ... };   // wrong target + wrong shape
  ```
  `controllerchange` is an event on `navigator.serviceWorker`, not a property on the
  registration; there is no `registration.oncontrollerchange`. This handler never runs, so
  after a new SW takes control the page is **not** reloaded — the user keeps seeing old
  content until a manual hard refresh. Correct form:
  `navigator.serviceWorker.addEventListener('controllerchange', () => { /* reload once */ });`

- **B4 — Already-`waiting` worker not surfaced on load.**
  `pwa-main.js.twig` only reacts to `onupdatefound` fired during the current page session; it
  never checks `registration.waiting` immediately after `register()`. In a corrected
  (non-eager-skipWaiting) design, an update that installed during a previous load would be
  missed.

- **B5 — Fetch handler cache hygiene / offline fallback.**
  `src/templates/pwa-sw.js.twig:90-109`: the success path caches **every** same-origin GET
  into `RUNTIME` via `cache.put(request, response.clone())` with no `response.ok` / status /
  `response.type` check — 4xx/5xx, opaque, and partial `206` responses poison the cache. The
  `catch` returns `caches.match(offlineUrl)`, which resolves to `undefined` if the offline
  page was not precached, yielding a broken `respondWith(undefined)`.

- **B6 — Dead code.** `src/templates/pwa-main.js.twig:37-60` is an `{% if false %}` block
  duplicating the `updatefound` logic. Remove it.

## 6. Goals

- The `#update-modal` appears **only** after an actual application deploy (SW version derived
  solely from deploy/app identity), not on routine revisits, session changes, or clock ticks.
- `AuroraPWA::version()` returns a value that is **stable across requests for a given deploy**
  and independent of the visitor's session.
- A genuine update produces a coherent flow: prompt (or silent) → activate new SW → reload
  once → user sees new content. No manual hard-refresh required.
- No `eval()` of config in the version path.
- Existing PWA tests pass; new regression tests pin the "version is stable per deploy"
  contract.

## 7. Non-goals

- Changing the production host app's own `config/packages/aurora.yaml`. We will fix the bundle
  and document the correct config; the host owner applies it.
- Rewriting the SW caching strategy wholesale (cache-first vs network-first, Workbox, etc.).
- Push notifications (`pwa-sw.js.twig:128-136` stays commented).
- Per-user *private content* caching. If that is genuinely required it is a separate design
  (runtime cache namespacing inside the SW), not the SW-version mechanism — see §9.

## 8. Proposed approach

### 8.1 Decouple the SW version from session data (fixes 3a)

Remove the session-attribute suffix from `version()`. The version becomes deploy identity
only: git hash (+ optional stable build id from `version_append`). Concretely, delete the
`AuroraPWA.php:318-336` cookie-reading + `_<sessionValue>` block. Keep the git hash as the
backbone (it is already APCu-cached for 24h in prod and only changes on deploy).

If per-session cache isolation turns out to be a real requirement (open question §9), do it
at runtime *inside* the SW (e.g. namespace the `RUNTIME` cache by a value read from a cookie
at fetch time), never by mutating the SW script bytes.

### 8.2 Make `version_append` safe and stable (fixes 3b)

- Drop the `!php/eval` path. Replace it with a typed host hook — e.g. call
  `App\Service\AuroraService::pwaVersionAppend(): string` if it exists (mirroring the existing
  `class_exists('\App\Service\AuroraService')` pattern already used in `manifestJSON()` /
  `mainJS()`), or accept a plain scalar/`%env()%` string. Document that the return value MUST
  be **stable for the lifetime of a deploy** (a build SHA, release tag, or `APP_VERSION` env),
  never time-based.
- Update `src/Resources/schema/packages/aurora.yaml:16-17`: remove the misleading
  `date('Y-m-d H')` comment (or replace it with a `%env(APP_VERSION)%` example) and stop
  shipping an `!php/eval` default.

### 8.3 Pick one coherent update UX and implement it end to end (fixes B1–B4, B6)

Recommended: **prompt-to-update** (least surprising; keeps the existing modal).

- `pwa-sw.js.twig`: do **not** `skipWaiting()` during `install`; let the new worker wait.
  Keep the `message`/`SKIP_WAITING` listener (`pwa-sw.js.twig:122-126`) so the page can
  trigger activation on the user's click.
- `pwa-main.js.twig`:
  - After `register()`, also check `registration.waiting` and call the modal if a worker is
    already waiting (B4).
  - Keep `onupdatefound` → on `installed` with an existing controller, show the modal.
  - Modal "Reload": `registration.waiting?.postMessage({type:'SKIP_WAITING'})` (now reliably
    present, B1/B2).
  - Add `navigator.serviceWorker.addEventListener('controllerchange', …)` that reloads the
    page exactly once (guard with a boolean to avoid reload loops) (B3).
  - Remove the dead `{% if false %}` block (B6) and the dead `registration.controllerchange`
    assignment.

Alternative: **silent auto-update** (no modal) — `skipWaiting()` + `clients.claim()` +
reload-once on `controllerchange`. Choose per product preference (§9).

### 8.4 Harden the fetch handler (fixes B5)

Only write to `RUNTIME` when `response && response.ok && response.type === 'basic'` (and not
a `206`). Guard the offline fallback: if `caches.match(offlineUrl)` is empty, return a
minimal synthesized `Response` instead of `undefined`.

## 9. Open questions

- **Is the per-session SW version intentional?** Why was `_<PHPSESSID>` added — to isolate
  cached private pages per user on shared devices? If yes, that need must be met at runtime
  (§8.1), not via the script bytes. If no, plain removal is safe. *(Decision needed before
  moving from `draft` to `active`.)*
- **What does `App\Utils::pwaVersionAppend()` return on the host app?** This likely confirms
  3b outright. If it is time-based, that alone explains the symptom and §8.2 is the priority
  fix.
- **Update UX:** silent auto-reload vs the existing prompt modal (§8.3)?
- **SW scope / path:** confirm the SW is served at a scope that covers the whole origin
  (`Service-Worker-Allowed` if served from a sub-path) — out of scope for the bug but worth a
  glance while in here.

## 10. Verification

- **Unit (bundle):** new tests in `tests/Utils/AuroraPWA/` asserting `version()` returns the
  **same** string across two requests that differ only in session attribute / cookie, and is
  independent of wall-clock — i.e. the regression that 3a/3b introduced cannot reappear.
  Extend `PWACacheTest` / `PWAMainJsTest` (which already stub `getHash()` and a session) to
  cover the stable-per-deploy contract. Per `.claude/rules/phpunit.md`, every new test method
  carries exactly one `#[Group(...)]`.
- **Static:** PHPStan stays green at level 6 (CI) — removing `eval()` should help, not hurt.
- **Manual (host app):**
  1. Load twice in one session, diff `/<sw-path>` → `PRECACHE`/`RUNTIME` identical (no
     trailing volatile segment).
  2. Load across a session expiry / re-login → still no modal.
  3. Deploy a real code change (git hash moves) → modal appears **once**; click Reload → new
     SW activates and the page reloads to new content automatically.
- **Regression guard:** after the fix, `App\Utils::pwaVersionAppend()` (or its replacement)
  is documented and verified to be stable across a deploy.
