# gesdinet/jwt-refresh-token-bundle

| Version | Created    | Updated    |
|---------|------------|------------|
| 2.0     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://github.com/markitosgv/JWTRefreshTokenBundle
* https://symfony.com/bundles/LexikJWTAuthenticationBundle/current/index.html

Provides refresh tokens on top of `lexik/jwt-authentication-bundle`. Exchange a valid (non-expired) refresh token at `POST /v1/token/refresh` for a new JWT + new refresh token. Bundle `^2.0` requires PHP 8.2+ and Symfony 6.4 / 7.2+ / 8.0+, so it runs unchanged on the v8.1 stub (PHP 8.5, Symfony 8.1) alongside `lexik/jwt-authentication-bundle ^3`.

## 1. Bundle Registration Is Manual

The Flex recipe in `symfony/recipes-contrib` does NOT reliably register the bundle in `config/bundles.php`. Dockraft's `symfony_install_skeleton()` (in `.docker/container/scripts/symfony.sh`, inside the API Platform conditional block) injects the entry manually after `composer require gesdinet/jwt-refresh-token-bundle`:

```php
return [
    // ...
    Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle::class => ['all' => true],
    // ...
];
```

If `cache:clear` reports `Unrecognized option "refresh_jwt" under "security.firewalls.api"`, the bundle is missing from `bundles.php` - re-run `symfony_install_skeleton()` (or add the entry manually).

Bundle FQCN: `Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle`.

## 2. Config Reference

Stub config at `config/packages/gesdinet_jwt_refresh_token.yaml`:

```yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000                                    # refresh token lifetime in seconds (default 2592000 = 30 days)
    refresh_token_class: App\Entity\RefreshToken    # the Doctrine entity that stores tokens
```

- **`refresh_token_class`** - required. Without it, the bundle falls back to an in-memory store and tokens vanish on container restart.
- **`ttl`** - opinionated default 30 days; tune per project security policy.

> **User provider resolution (v2.0+).** The `user_provider` key was REMOVED from `gesdinet_jwt_refresh_token:` in `gesdinet/jwt-refresh-token-bundle` v2.0. Declaring it under v2.0+ produces `Unrecognized option "user_provider"` at `cache:clear`. The bundle now derives the user provider exclusively from the firewall configuration in `security.yaml` - set `firewalls.<name>.provider:` to the desired provider (the stub firewall `api` already declares `provider: all_users`). Dockraft is targeting v2.0+; do NOT add `user_provider` back into this YAML.

## 3. Firewall Integration - `refresh_jwt:` (UNDERSCORE!)

In `security.yaml.example`, the listener is registered under the firewall:

```yaml
security:
    firewalls:
        api:
            pattern: ^/v1
            stateless: true
            jwt: ~
            refresh_jwt:
                check_path: /v1/token/refresh   # MUST match a real route in routes.yaml
```

**Critical:** the option name is `refresh_jwt` (underscore), NOT `refresh-jwt` (hyphen). Symfony Security YAML keys are strict - `refresh-jwt:` produces `Unrecognized option "refresh-jwt"` at every `cache:clear`, blocking the entire install (including the deferred `lexik:jwt:generate-keypair` step).

The `check_path` value must point to a route declared in `config/routes.yaml`. Stub layout:

```yaml
api_refresh_token:
    path: /v1/token/refresh
    methods: ['POST']
```

## 4. Entity Setup

Stub ships `src/Entity/RefreshToken.php`:

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Entity]
class RefreshToken extends BaseRefreshToken
{
    // ...
}
```

Notes:
- Extends `Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken` (the concrete class), NOT `AbstractRefreshToken`.
- Table name is `refresh_tokens` (plural). Do not change unless you also update any DBAL queries that reference it.
- The bundle handles all CRUD on this table - application code typically reads tokens (audit, revocation lookups), never writes them directly.

## 5. Token Rotation Behavior

By default (gesdinet 2.x), every successful refresh:

1. Validates the incoming refresh token (must exist, not be expired, match the user).
2. Issues a NEW JWT.
3. Issues a NEW refresh token (with fresh `ttl`).
4. Marks the old refresh token as USED in the DB (kept for audit, but unusable).

Clients must store and send the LATEST refresh token returned. Implementations that cache the original refresh token across multiple refresh cycles fail after the first refresh with `JWT Refresh Token Not Found`.

## 6. Test Environment

Stub's `security.yaml.example` already disables login throttling in tests so that integration suites exercising the refresh flow do not hit the 5-attempts-per-minute limit:

```yaml
when@test:
    security:
        firewalls:
            api:
                login_throttling: false
```

When writing tests that issue many refreshes (e.g. token rotation tests), this is mandatory.

## 7. Common Errors - Debug Checklist

| Error | Cause | Fix |
|---|---|---|
| `Unrecognized option "refresh-jwt"` under firewall | Hyphen instead of underscore | Rename to `refresh_jwt:` in `security.yaml` |
| `Unrecognized option "refresh_jwt"` under firewall | Bundle not registered in `bundles.php` | Verify `GesdinetJWTRefreshTokenBundle` line; re-run `symfony_install_skeleton()` |
| `Unrecognized option "user_provider"` under `gesdinet_jwt_refresh_token` | Legacy v1.x config left in YAML after v2.0+ install | Remove `user_provider:` line from `gesdinet_jwt_refresh_token.yaml`; ensure firewall has `provider:` set in `security.yaml` |
| `There is no user provider for user "App\Entity\User"` | Firewall lacks `provider:` setting (or it points to a non-existent provider) | Set `firewalls.<name>.provider: all_users` in `security.yaml` (NOT in `gesdinet_jwt_refresh_token.yaml` - that key was removed in v2.0) |
| `JWT Refresh Token Not Found` | Client sent expired/old/used token | Client must store the LATEST token from each rotation |
| `Class "App\Entity\RefreshToken" does not exist` | Entity missing or namespace wrong | Verify `src/Entity/RefreshToken.php` extends the correct base class |
| `SQLSTATE[42P01]: Undefined table: refresh_tokens` | Migration not run | `bin/console doctrine:migrations:migrate` |

## 8. Version Constraints

| Package | Required |
|---|---|
| `gesdinet/jwt-refresh-token-bundle` | `^2.0` (PHP 8.2+, Symfony 8 + `lexik/jwt-authentication-bundle ^3` transitive). The stub YAML is v2.0-shaped - `user_provider` is intentionally absent. |
| `lexik/jwt-authentication-bundle` | `^3` (Symfony 8 compatible; installed by `symfony_install_skeleton()` immediately before gesdinet) |

Both are gated on `DKZ_PHP_SYMFONY_API_PLATFORM_VERSION_INSTALL != "0"` in `symfony.sh`. If API Platform is disabled, neither bundle is installed and `gesdinet_jwt_refresh_token.yaml.example` is removed by the cleanup branch.
