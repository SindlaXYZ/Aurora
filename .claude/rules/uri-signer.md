# UriSigner - Signed URLs

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/components/http_kernel.html#signed-uris
* https://symfony.com/doc/8.1/routing.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

`Symfony\Component\HttpKernel\UriSigner` produces HMAC-signed URLs with optional expiration. The capabilities the stub relies on (introduced across 7.3 / 7.4) remain valid on 8.1:

1. A `verify()` method that throws **specific** exceptions on failure (instead of returning a single boolean).
2. Native `ClockInterface` integration so signed URLs are testable without re-implementing time.
3. The `#[IsSignatureValid]` controller attribute.

Symfony 8.1 does not add a new UriSigner API, but it DOES change one runtime behavior of `#[IsSignatureValid]`: HEAD requests no longer bypass its `methods:` filter (see §5.4).

Use signed URLs for:

- Email magic links (password reset, account verification, unsubscribe).
- Short-lived download links to private files.
- Webhook callback URLs back into the application that must not be tampered with by the third party.

Stub: no signed-URL flows are scaffolded yet. This rule covers the canonical pattern when you add one.

## 1. Service

`UriSigner` is autowired by default. Inject it directly:

```php
use Symfony\Component\HttpKernel\UriSigner;

final readonly class MagicLinkGenerator
{
    public function __construct(
        private UriSigner $signer,
        private UrlGeneratorInterface $urls,
    ) {}

    public function build(User $user, int $ttlSeconds = 3600): string
    {
        $url = $this->urls->generate(
            'account_magic_link',
            ['userId' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        // Clock-aware. The signer reads the current time from the configured clock service.
        return $this->signer->sign($url, expiration: new \DateInterval('PT' . $ttlSeconds . 'S'));
    }
}
```

## 2. Verification - `verify()`, NOT `check()`

`check(string $uri): bool` still works but conflates several failure modes. The `verify()` method throws specific exceptions:

| Exception | Meaning |
|---|---|
| `UnSignedUriException` | URL has no `_hash` parameter. The producer never signed it. |
| `UnverifiedSignedUriException` | URL has a `_hash` but the HMAC does NOT match. Tampered or wrong secret. |
| `ExpiredSignedUriException` | Signature was valid but `_expiration` is in the past. |

```php
use Symfony\Component\HttpKernel\Exception\{ExpiredSignedUriException, UnSignedUriException, UnverifiedSignedUriException};

#[Route('/magic/{userId}', name: 'account_magic_link')]
public function consume(Request $request, int $userId): Response
{
    try {
        $this->signer->verify($request->getUri());
    } catch (UnSignedUriException) {
        throw $this->createNotFoundException();           // pretend the route doesn't exist
    } catch (UnverifiedSignedUriException) {
        $this->logger->warning('Magic link tampered.', ['userId' => $userId]);
        throw $this->createAccessDeniedException();
    } catch (ExpiredSignedUriException) {
        return $this->render('account/magic-link-expired.html.twig');
    }

    // ... sign in $userId
}
```

Rule: **handle each exception SEPARATELY.** A blanket `catch (\Exception)` defeats the purpose - you lose the "expired vs tampered vs missing" distinction that drives UX and security logging.

## 3. Rules

1. **Always set an expiration.** Signed URLs without `_expiration` are valid forever. For email links: 1 h is generous; 24 h is sloppy unless the use case demands it.
2. **The signer's secret comes from `APP_SECRET`.** Rotating `APP_SECRET` invalidates ALL outstanding signed URLs in the wild. Coordinate this with email retention policy.
3. **Do NOT log the full signed URL.** It is a credential. Log the route name, user id, expiration timestamp - never the `_hash` or full URL.
4. **`verify()` mutates nothing.** Safe to call in `EventSubscriber` or `Voter`. Call it from the controller (or from a kernel.request listener) - never from a background worker that consumed the URL hours ago (expiration check would have moved on).
5. **In tests, fix the clock.** Inject `MockClock` so signed URLs produced by test fixtures don't expire based on wall time. `UriSigner` reads from the registered `ClockInterface` service.

## 4. Testing Pattern

```php
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\UriSigner;

final class MagicLinkGeneratorTest extends KernelTestCase
{
    public function testLinkExpiresAfterTtl(): void
    {
        $clock = new MockClock('2026-01-01 00:00:00');
        self::getContainer()->set('clock', $clock);

        $generator = self::getContainer()->get(MagicLinkGenerator::class);
        $signer    = self::getContainer()->get(UriSigner::class);

        $url = $generator->build($user = new User(...), ttlSeconds: 60);

        $signer->verify($url);                                 // valid right now

        $clock->modify('+61 seconds');

        $this->expectException(ExpiredSignedUriException::class);
        $signer->verify($url);
    }
}
```

## 5. `#[IsSignatureValid]` Attribute

Replaces the boilerplate try/catch around `$signer->verify()` with a controller attribute. The framework verifies BEFORE the controller method runs and converts each failure into the right HTTP response.

```php
use Symfony\Component\HttpKernel\Attribute\IsSignatureValid;
use Symfony\Component\Routing\Attribute\Route;

final class MagicLinkController
{
    #[Route('/magic/{userId}', name: 'account_magic_link')]
    #[IsSignatureValid]
    public function consume(int $userId): Response
    {
        // signature already verified - focus on the business logic
        // ...
    }

    // Restrict the check to specific methods (POST + PUT only):
    #[Route('/webhook/{provider}', methods: ['GET', 'POST', 'PUT'])]
    #[IsSignatureValid(methods: ['POST', 'PUT'])]
    public function webhook(Request $request, string $provider): Response { /* ... */ }
}
```

Class-level form (applies to every method):

```php
#[IsSignatureValid]
final class DownloadController
{
    #[Route('/download/{id}')]
    public function download(int $id): Response { /* ... */ }
}
```

Rules:

1. **Failure mapping is opinionated.** `UnSignedUriException` -> 404, `UnverifiedSignedUriException` -> 403, `ExpiredSignedUriException` -> 410. If the project needs different status codes (e.g. show an expired link page instead of a 410 body), revert to manual `verify()` (section 2).
2. **`methods:` restricts the CHECK, not the route.** A route with `methods: ['GET', 'POST']` plus `#[IsSignatureValid(methods: ['POST'])]` accepts both verbs but only validates POST. Reach for this when the GET form is the user clicking a link (already authenticated) and POST is an automated callback that must be signed.
3. **Combine with `#[IsGranted]` deliberately.** Signature validity proves the URL was issued by us; it does NOT prove the consumer is the intended recipient. For per-user resources, also check `#[IsGranted('ROLE_USER')]` or a custom voter that compares the URL's user ID against `getUser()->getId()`.

### 5.4 Behavior Change in 8.1 - HEAD No Longer Bypasses `methods:`

Before 8.1, a HEAD request could slip past the `methods:` filter of `#[IsSignatureValid]` (HEAD was treated like GET, so depending on the configured `methods:` the signature check was skipped). Symfony 8.1 fixes this (security fix CVE-2026-45075): HEAD requests are now subject to the same `methods:` filter as any other verb.

This is the same fix that touches `#[IsGranted]` and `#[IsCsrfTokenValid]` - see `security.md` §5.5 for the broader description. Practical consequence for signed URLs:

- A route whose `#[IsSignatureValid(methods: [...])]` list was tuned assuming HEAD was exempt will now run the signature check for HEAD too. If a HEAD probe used to reach the action without a valid signature, it will now fail with the opinionated mapping in Rule 1 (typically 404 for an unsigned URL).
- Re-test any signed route that receives HEAD requests (link-preview crawlers and uptime monitors commonly send HEAD) after upgrading to 8.1.

## 6. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `UnSignedUriException` on a URL you generated | Forgot to call `$signer->sign(...)`; or stripped `_hash` somewhere mid-pipeline (URL rewriting in nginx, query-string sanitisation in a middleware) | Verify the URL leaves the application AS-IS up to the email body. |
| `UnSignedUriException` only on HEAD requests after upgrading to 8.1 | The HEAD-bypass fix now enforces the `methods:` filter for HEAD (§5.4) | Add HEAD to the attribute's `methods:` if HEAD probes are expected to pass, OR ensure the probe sends a signed URL. |
| Every link reports `ExpiredSignedUriException` immediately | Container time skew (Docker container vs host) | Synchronize the container time via `TZ` env + NTP on the host, OR verify the test uses `MockClock`. |
| Hash mismatch after deploy | `APP_SECRET` rotated | Either don't rotate, or invalidate the affected emails (delete pending notifications, send fresh ones). |
| `check()` returns false but no detail | Using the legacy method | Switch to `verify()` for clear exception types. |

## 7. Version Constraints

| Package | Required |
|---|---|
| `symfony/http-kernel` | `^7.3` (for `verify()` + the three dedicated exceptions + ClockInterface integration), `^7.4` (for `#[IsSignatureValid]`), `^8.1` (for the HEAD `methods:` enforcement fix on `#[IsSignatureValid]`) |
| `symfony/clock` | `^7.3` (already a transitive dep; needed for `MockClock` in tests) |
