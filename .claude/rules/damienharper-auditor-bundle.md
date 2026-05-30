# damienharper/auditor-bundle

| Version | Created    | Updated    |
|---------|------------|------------|
| 7.2     | 2026-05-08 | 2026-05-29 |

**Sources:**
* https://github.com/DamienHarper/auditor-bundle
* https://github.com/DamienHarper/auditor

## 1. Canonical Attribute Namespace

Use `DH\Auditor\Attribute\*`. The `DH\Auditor\Provider\Doctrine\Auditing\Attribute\*` paths are `@deprecated` in `damienharper/auditor` 4.x (the legacy classes exist only as backward-compatibility aliases that extend the canonical ones).

| Attribute | Use this (canonical) | Deprecated alias |
|-----------|----------------------|------------------|
| `Auditable` (class) | `DH\Auditor\Attribute\Auditable` | `DH\Auditor\Provider\Doctrine\Auditing\Attribute\Auditable` |
| `Ignore` (property) | `DH\Auditor\Attribute\Ignore` | `DH\Auditor\Provider\Doctrine\Auditing\Attribute\Ignore` |
| `Security` (class) | `DH\Auditor\Attribute\Security` | `DH\Auditor\Provider\Doctrine\Auditing\Attribute\Security` |
| `DiffLabel` (property) | `DH\Auditor\Attribute\DiffLabel` | (new in 4.x) |

Standard import pattern in entities:

```php
use DH\Auditor\Attribute as Audit;

#[Audit\Auditable]
#[Audit\Security(view: [User::ROLE_ADMIN])]
class Company { ... }
```

Annotations (`DH\Auditor\Provider\Doctrine\Auditing\Annotation\*`) were removed in v7 - only PHP 8 attributes are supported.

## 2. Entity Registration Is Required

`#[Auditable]` alone does **not** enable auditing. Every audited entity must also be listed under `dh_auditor.providers.doctrine.entities` in `config/packages/dh_auditor.yaml`. There is no "audit everything" mode.

```yaml
dh_auditor:
    providers:
        doctrine:
            entities:
                App\Entity\Company: ~
                App\Entity\Address: ~
                App\Entity\UserCompany: ~
```

`~` enables auditing with default options. Per-entity overrides (`enabled`, `ignored_columns`, `roles.view`) go in the same block.

## 3. Mark Sensitive Properties with `#[Audit\Ignore]`

Passwords, JWT/refresh tokens, API keys, MFA secrets, and similar values must never enter the audit diff column.

```php
#[ORM\Column]
#[Audit\Ignore]
private string $password;
```

Equivalent global option in YAML: `entities.<FQCN>.ignored_columns: [password, plainPassword]`. Use the YAML form when the property is on a third-party entity that you cannot annotate.

## 4. DQL and DBAL Writes Are NOT Audited

The provider hooks Doctrine's `UnitOfWork` on `flush()`. Operations that bypass the UnitOfWork are silently invisible to the audit log:

- DQL `UPDATE` / `DELETE` queries
- Raw SQL via `Connection::executeStatement()` or `bin/console dbal:run-sql`
- Native batch import scripts

For changes that must be audited, route them through ORM `persist()` / `remove()` + `flush()`.

## 5. Viewer Access Control Is Opt-In

The bundle does NOT lock `/audit` by default. Restrict it in `config/packages/security.yaml`:

```yaml
security:
    access_control:
        - { path: ^/audit, roles: ROLE_ADMIN }
```

Per-entity view filtering uses `#[Audit\Security(view: [...])]` and is independent of the route ACL - both layers should be configured.

## 6. Reader API - Direct Property Access

The `Entry` model uses **direct property access**; getters were removed in v7:

```php
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;

foreach ($reader->createQuery(Company::class)
    ->addFilter(new SimpleFilter(Query::TYPE, 'update'))
    ->addOrderBy(Query::CREATED_AT, 'DESC')
    ->execute() as $entry
) {
    $entry->type;          // insert | update | remove | associate | dissociate
    $entry->objectId;
    $entry->diffs;         // array (already JSON-decoded)
    $entry->blame;         // ['user_id', 'username', 'user_fqdn', 'firewall']
    $entry->createdAt;     // DateTimeImmutable
    $entry->transactionId; // ULID
}
```

Filter classes (under `DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\`): `SimpleFilter`, `DateRangeFilter`, `RangeFilter`, `NullFilter`, `JsonFilter`.

## 7. Migrations

The provider hooks `postGenerateSchemaTable`, so audit tables appear in generated migrations automatically. After marking a new entity `#[Auditable]` and registering it in `dh_auditor.yaml`:

```bash
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:migrations:diff
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:migrations:migrate -n
```

Audit tables follow `{prefix}{table}{suffix}` (default suffix: `_audit`). Retention via `bin/console audit:clean P6M` (ISO-8601 duration).

## 8. Version Constraints

| Package | Required version |
|---------|------------------|
| `damienharper/auditor-bundle` | `^7.2` (Symfony 8, PHP 8.4+, Doctrine ORM 3, DBAL 4) |
| `damienharper/auditor` | `^4.3` |

Bundle FQCN: `DH\AuditorBundle\DHAuditorBundle` (NOT the legacy `DamienHarper\Bundle\DoctrineAuditBundle\DoctrineAuditBundle` from versions <= 4.x).
