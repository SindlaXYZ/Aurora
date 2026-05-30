# Symfony Security - Error Exposure, OAuth2 / OIDC, and 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-security-improvements
* https://symfony.com/doc/8.1/security.html
* https://symfony.com/doc/8.1/security/access_control.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

This rule covers the security configuration the stub uses (or references for opt-in): the 7.3 baseline (`expose_security_errors`, OAuth2 introspection, OIDC discovery) that remains valid on 8.1, plus the 8.1 additions and one behavior change to re-test on upgrade. Auth bundles ship their own rules: see `damienharper-auditor-bundle.md`. The JWT refresh-token bundle rule lives in the v8.0 stub (`gesdinet-jwt-refresh-token-bundle.md`) and applies unchanged on 8.1.

## 1. `expose_security_errors` - Authentication Error Detail Level

Replaces the legacy boolean `hide_user_not_found` (removed in 8.0). Declared as a **top-level** key under `security:` in `security.yaml` - NOT under a firewall. The setting is global and applies to every firewall (the security bundle exposes a single configuration node, `security.expose_security_errors`, defined on the root in `Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration::getConfigTreeBuilder()`).

```yaml
# config/packages/security.yaml
security:
    expose_security_errors: 'none'   # top level, NOT under firewalls.<name>

    firewalls:
        api:
            pattern: ^/v1
            # ... NO `expose_security_errors:` here - placing it under a firewall fails with:
            # "Internal error: Unrecognized option "expose_security_errors" under "security.firewalls.api""
```

| Level | Behaviour |
|---|---|
| `none` (DEFAULT, used by the stub) | All user-related exceptions are wrapped as `BadCredentialsException`. The API tells the client "wrong credentials" regardless of whether the user exists, the password is wrong, or the account is locked. Safest for public APIs - no user enumeration. |
| `account_status` | If the password is CORRECT but the account is locked / disabled / expired, the real `AccountStatusException` is returned. Useful when the UX wants to tell legitimate users "your account is locked; contact support." |
| `all` | All security exceptions pass through unchanged. **DEV / debugging ONLY**. Leaks the existence of users and the exact cause of every failure. |

In 8.0+ the `AuthenticatorManager` `$exposeSecurityErrors` argument accepts only `Symfony\Bundle\SecurityBundle\Security\ExposeSecurityLevel` enum cases - the string config value above is mapped to the enum by the bundle, so application code never deals with the raw string.

Rules:

1. **Top-level only.** `expose_security_errors:` belongs directly under `security:`. Placing it under `security.firewalls.<name>:` raises `Unrecognized option "expose_security_errors" under "security.firewalls.<name>"` at `cache:clear`, blocking the entire build.
2. **Keep `'none'` on public APIs.** Public-facing firewalls (e.g. `^/v1`) should stay on `none` to prevent user enumeration. Only enable `account_status` when the project genuinely has an internal admin firewall behind additional access controls, AND a UX requirement to tell legitimate users that their account is locked.
3. **Never set `'all'` in production.** Reserve `all` for local debugging. It leaks the existence of users and the precise cause of every authentication failure.

## 2. OAuth2 Token Introspection (RFC 7662)

When the API trusts opaque bearer tokens issued by an external Identity Provider that exposes a `/oauth2/introspect` endpoint, Symfony can validate them per request without local key material:

```yaml
# framework.yaml
framework:
    http_client:
        scoped_clients:
            oauth2.client:
                base_uri: 'https://idp.example.com/introspection'
                headers:
                    Authorization: 'Basic <base64(client_id:client_secret)>'

# security.yaml (firewall)
security:
    firewalls:
        api:
            access_token:
                token_handler:
                    oauth2: ~
```

Use when: the IdP issues opaque tokens (no JWT payload) AND the IdP exposes an introspection endpoint.
Avoid when: the IdP issues JWTs - prefer OIDC discovery (next section) so validation stays local and avoids the per-request HTTP roundtrip.

## 3. OIDC Discovery

When the IdP exposes `/.well-known/openid-configuration` (Auth0, Keycloak, Cognito, Azure AD, etc.), Symfony can fetch issuer / JWKS / endpoints automatically. The stub's `security.yaml.example` ships a commented block ready to uncomment:

```yaml
security:
    firewalls:
        api:
            access_token:
                oidc:
                    claim: 'email'
                    audience: 'symfony'
                    issuers: ['https://idp.example.com/']
                    enforce_key_usage_verification: true   # 8.1+ - see §5.3
                    discovery:
                        base_uri: 'https://idp.example.com/oidc/realms/master/'
                        cache:
                            id: cache.app
```

In 8.0+ the OIDC token handler accepts only `AlgorithmManager` / `JWKSet` objects for its signature algorithm / keyset; the YAML keys are `algorithms` and `keyset` (the legacy singular `algorithm` / `key` options were removed in 8.0). The stub's commented block uses the discovery form above, which derives both from the IdP metadata.

Rules:

1. **Always set `audience` and `issuers`.** Tokens that match `discovery.base_uri` but a different audience or issuer are valid by default - pin both.
2. **Always set `discovery.cache.id`.** Without caching, the bundle calls `/.well-known/openid-configuration` on every authenticated request. `cache.app` is a sane default.
3. **Pick the `claim` deliberately.** It determines which value Symfony uses as the user identifier (later looked up by the user provider). `'email'` is common; `'sub'` (the IdP's stable subject id) is preferred when emails are mutable.
4. **`access_token` is mutually exclusive with `jwt:` / `json_login:` on the SAME firewall.** Either rip them out, or define a SEPARATE firewall with a different pattern (e.g. `^/v2` for OIDC, `^/v1` for the local JWT flow).
5. **Set `enforce_key_usage_verification: true` (8.1+)** when the IdP's JWKS marks signing keys with a `use` / `key_ops` claim - see §5.3.

## 4. Test-Environment Login Throttling

The stub disables `login_throttling` under `when@test`. This is mandatory whenever tests exercise the auth flow more than 5 times per minute (refresh-token rotation tests, brute-force protection tests, multi-user functional tests). Do NOT remove this override without replacing it with a deterministic alternative (mock rate limiter).

`login_throttling` itself is a configurable rate limiter under the hood; for compound limits combine it with the rate-limiter component (the compound-limiter pattern is documented in the v8.0 stub's `rate-limiter.md`, which is unchanged on 8.1).

> **Do NOT adopt a per-username login rate-limit as a built-in 8.1 feature.** A per-username login rate-limit was added during the 8.1 development cycle and then **reverted before release** - it is NOT part of Symfony 8.1. Keep using `login_throttling` plus the rate-limiter component for brute-force protection.

## 5. Symfony 8.1 Additions

All additions below are **additive** - existing 8.0 security config keeps working.

### 5.1 `this` in `#[IsGranted]` Subject Expressions

When an `#[IsGranted]` attribute carries a `subject` that is an `Expression`, the controller instance is now available as `this` in the expression variables (when a subject exists), alongside the usual `user`, `token`, `request`, `subject`, and so on. This lets the access expression call a method on the controller to compute the subject inline instead of pushing that logic into a voter.

```php
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController
{
    #[Route('/reports/{id}', methods: ['GET'])]
    #[IsGranted(
        'VIEW',
        subject: new Expression('this.resolveReport(args["id"])'),
    )]
    public function show(int $id): Response
    {
        // ...
    }

    public function resolveReport(int $id): Report
    {
        // ...
    }
}
```

Prefer a dedicated voter when the access logic is non-trivial or reused across actions; reach for `this` in the expression only for a thin, action-local subject lookup.

### 5.2 `erase_credentials` Is Deprecated

Symfony 8.1 deprecates the `erase_credentials` security config key, its container parameter, AND the corresponding `AuthenticatorManager` constructor argument. This aligns with the earlier deprecation (and 8.0 removal) of `UserInterface::eraseCredentials()` / `TokenInterface::eraseCredentials()`.

Stop relying on credential erasure performed by the framework. Wipe sensitive data instead via:

- **DTOs** at the API boundary - the `User` entity never holds the plain password in the first place.
- **`AuthenticationTokenCreatedEvent`** to redact runtime tokens.
- **`__serialize()`** on the user / token to exclude sensitive properties from serialization (this is the 8.0 replacement for the removed `eraseCredentials()` methods).

Do NOT add `erase_credentials` to `security.yaml` in new code; if an existing project sets it, remove it and confirm sensitive fields are handled by one of the mechanisms above.

### 5.3 `enforce_key_usage_verification` on OIDC Discovery

A new `enforce_key_usage_verification` option on the OIDC discovery token handler (shown in §3). When `true`, the handler verifies that the JWKS key selected to validate a token is actually marked for signing (`use: "sig"` / appropriate `key_ops`), rejecting keys published for a different purpose.

Set it to `true` when the IdP publishes typed keys; leave it at the default when the IdP's JWKS does not annotate key usage (turning it on against an un-annotated JWKS rejects otherwise-valid tokens).

### 5.4 Smaller Security Additions

- **Role hierarchy:** retrieval of parent role names from the role hierarchy is now exposed - useful when code needs to expand a role into the full set it implies.
- **`SignatureHasher::computeSignatureHash()` accepts enums** as signature payload values (relevant to remember-me / signed-login-link hashing).
- **Logout:** the framework now sends a more complete set of `Clear-Site-Data` directives on logout.

### 5.5 Behavior Change to Re-Test on Upgrade - HEAD No Longer Bypasses `methods:`

Before 8.1, a HEAD request could slip past the `methods:` filter of `#[IsGranted]`, `#[IsCsrfTokenValid]`, and `#[IsSignatureValid]` (HEAD was treated like GET and, depending on the configured methods, the attribute's check was skipped). Symfony 8.1 fixes this (security fix CVE-2026-45075): HEAD requests are now subject to the same `methods:` filter as any other verb.

Practical consequences:

- A route that (accidentally) relied on HEAD slipping past `#[IsGranted(... , methods: [...])]` will now have the check enforced for HEAD too. Audit any attribute whose `methods:` list was tuned assuming HEAD was exempt.
- The same change touches `#[IsSignatureValid]` - see `uri-signer.md` §5 for the signed-URL angle.
- This is the single behavior change in this rule that can alter runtime authorization on upgrade. Re-test routes that receive HEAD requests against these three attributes.

## 6. Version Constraints

| Package | Required |
|---|---|
| `symfony/security-bundle` | `^7.3` (for `expose_security_errors`, `oauth2:` and `oidc:` token handlers), `^8.1` (for `this` in `#[IsGranted]` subject expressions, the `erase_credentials` deprecation, `enforce_key_usage_verification` on OIDC discovery, parent-role retrieval, enum support in `SignatureHasher`, the completed `Clear-Site-Data` directives, and the HEAD `methods:` enforcement fix) |
| `symfony/security-core` | `^8.1` (role-hierarchy parent-name retrieval, `SignatureHasher` enum support) |
