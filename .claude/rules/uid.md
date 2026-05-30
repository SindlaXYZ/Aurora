# UID Component - UUID v7 Default, MockUuidFactory, Uuid47Transformer

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/components/uid.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://datatracker.ietf.org/doc/html/rfc9562

The Uid component provides UUIDs (RFC 9562) and ULIDs. UUID v7 is the default factory output and `MockUuidFactory` makes generation deterministic in tests - both carried from the 7.4 line and still current on 8.1. On top of that, Symfony 8.1 adds two **additive** features documented here: `Uuid47Transformer` (UUIDv7 <-> UUIDv4 conversion) and a `$format` argument on `Ulid::isValid()`.

## 1. What Changed in Symfony 8.1

All 8.1 additions are additive - existing 7.x code keeps working.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Expose a UUIDv7 externally as a UUIDv4-looking value | Not built in | `Uuid47Transformer::encode()` / `decode()` - reversible, keyed by a secret (section 5) |
| Validate a ULID in a specific textual representation | `Ulid::isValid($value)` only (Base32) | `Ulid::isValid($value, $format)` with `FORMAT_BASE_32` / `FORMAT_BASE_58` / `FORMAT_RFC_4122` (section 6) |

The UUID v7 default and `MockUuidFactory` (sections 2-4) are unchanged from 7.4.

## 2. UUID v7 Is the Default

`UuidFactory::create()` returns a `UuidV7` by default. UUID v7 is time-ordered, monotonic per microsecond, and database-friendly - its lexicographic order matches creation order, so indexes on UUID-keyed tables stay efficient.

```php
use Symfony\Component\Uid\Factory\UuidFactory;

final readonly class IdGenerator
{
    public function __construct(private UuidFactory $uuids) {}

    public function next(): string
    {
        return (string) $this->uuids->create();   // UUID v7
    }
}
```

Override per environment if a project legitimately needs a different version (the `framework.uid` config node is unchanged on 8.1):

```yaml
# config/packages/uid.yaml
framework:
    uid:
        default_uuid_version: 7
        time_based_uuid_version: 7
```

## 3. Why v7 Over Older Versions

| Version | Use case | Trade-off |
|---|---|---|
| v4 (random) | Maximum entropy, security tokens | Random insert order -> index bloat on B-tree primary keys |
| v6 (rearranged v1) | Time-ordered, fixed MAC | Lower microsecond precision; exposes the host MAC by default |
| v7 (RFC 9562) | Time-ordered, random tail, microsecond precision | The default; chooses well for primary keys |
| ULID | Time-ordered, 26-char Crockford base32 | Use when the textual form must be shorter than UUID |

Rule of thumb: **use v7 for ENTITY IDs**, use **v4 for SECURITY TOKENS** (password reset, session IDs).

## 4. Doctrine Mapping

```php
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    public function getId(): UuidV7
    {
        return $this->id;   // factory returns v7 by default
    }
}
```

Rule: **type the property as `Uuid`** (parent class) at the entity level, **type the getter return as `UuidV7`** when the project guarantees v7. Mixing v4 / v7 in the same column is fine technically - the field is still 128-bit binary - but it complicates audit / debugging.

## 5. `MockUuidFactory` in Tests

`MockUuidFactory` makes UUIDs deterministic without touching application code:

```php
use Symfony\Component\Uid\Factory\MockUuidFactory;
use Symfony\Component\Uid\UuidV7;

public function testCreateOrderAssignsUuid(): void
{
    $factory = new MockUuidFactory([
        UuidV7::fromString('01910000-0000-7000-8000-000000000001'),
        UuidV7::fromString('01910000-0000-7000-8000-000000000002'),
    ]);

    self::getContainer()->set(UuidFactory::class, $factory);

    $order1 = $service->createOrder();
    $order2 = $service->createOrder();

    self::assertSame('01910000-0000-7000-8000-000000000001', (string) $order1->getId());
    self::assertSame('01910000-0000-7000-8000-000000000002', (string) $order2->getId());
}
```

In addition to `create()`, the same pre-seeded queue works for `randomBased()`, `timeBased()`, and `nameBased()`.

## 6. `Uuid47Transformer` - UUIDv7 <-> UUIDv4 Conversion (8.1 NEW)

Lets a project keep UUID v7 internally (time-ordered, index-friendly) while exposing a UUIDv4-looking value externally - so the creation time encoded in a v7 is not leaked in public IDs. The conversion is **reversible** and keyed by a secret.

```php
use Symfony\Component\Uid\Uuid47Transformer;
use Symfony\Component\Uid\UuidV7;

// The secret must be at least 16 bytes.
$transformer = new Uuid47Transformer($secret);

$v7 = new UuidV7();

$v4 = $transformer->encode($v7);        // returns a UuidV4 instance (no embedded timestamp visible)
$original = $transformer->decode($v4);  // returns the original UuidV7

$original->equals($v7);                 // true
```

Rules:

1. **The secret is a credential.** Provide it via an env var (`%env(...)%`), never hard-code it. It must be at least 16 bytes. Rotating it breaks the round-trip for every previously-encoded value still in the wild - treat it like `APP_SECRET`.
2. **`encode()` -> a `UuidV4` instance, `decode()` -> the original `UuidV7`.** The encoded form is structurally a v4 (random-looking); only `decode()` with the same secret recovers the v7.
3. **Use it only when the v7 timestamp must be hidden.** If the creation time is not sensitive, store and expose the v7 directly (section 2) - the transformer adds a keyed step on every boundary crossing.
4. **Persist the v7, transform at the edge.** Keep the database column as the v7 (so ordering / indexing benefits hold); `encode()` only when writing the value into a public URL / payload and `decode()` only when reading one back.

## 7. `Ulid::isValid()` with `$format` (8.1 NEW)

`Ulid::isValid()` gains an optional `$format` argument to validate a ULID in a specific textual representation (default is Base32).

```php
use Symfony\Component\Uid\Ulid;

$isValid = Ulid::isValid($ulidValue);                          // default: Base32
$isValid = Ulid::isValid($ulidValue, Ulid::FORMAT_BASE_32);
$isValid = Ulid::isValid($ulidValue, Ulid::FORMAT_BASE_58);
$isValid = Ulid::isValid($ulidValue, Ulid::FORMAT_RFC_4122);
```

Rule: **pass the `$format` that matches how the value reaches you.** A ULID stored / transmitted as Base58 or as an RFC 4122 string fails the default Base32 check; validate against the representation the producer actually used.

## 8. Rules

1. **Default to v7 for entity primary keys.** No code change needed - the factory does it.
2. **Never call `Uuid::v4()` / `new UuidV4()` directly in services.** Inject `UuidFactory` so tests can swap to `MockUuidFactory`. Constructing UUIDs directly = untestable timestamp / randomness.
3. **Public IDs should be UUIDs, not auto-increment integers.** Sequential ints leak business volume (`/orders/42` -> `/orders/43`); UUIDs do not. When even the v7 timestamp must be hidden, expose the `Uuid47Transformer`-encoded form (section 6).
4. **String form in URLs.** Use UUIDs in their canonical string form (`d3b07384-...`) in routes; pair with `Requirement::UUID_V7` to validate them. Do NOT pass binary form through user-facing URLs.
5. **One factory per project.** Re-using `UuidFactory` across services keeps the override surface flat - one mock factory in tests replaces them all.

## 9. Common Errors

| Error | Cause | Fix |
|---|---|---|
| Tests are non-deterministic on UUID-keyed fixtures | Code calls `Uuid::v7()` directly | Inject `UuidFactory` and replace with `MockUuidFactory` in tests. |
| Doctrine queries slow on UUID PK | Wrong default version (v4) producing random order | Verify `framework.uid.default_uuid_version: 7`. |
| `MockUuidFactory queue exhausted` | More service calls than pre-seeded UUIDs | Seed enough UUIDs; or set `$factory->setReturnRandomOnExhausted(true)` if order-by-arrival is irrelevant. |
| `Uuid47Transformer::decode()` returns a different UUID than expected | Secret differs from the one used to `encode()` | The same secret must be used on both sides; a rotated secret breaks the round-trip. |
| `Ulid::isValid()` returns false for a value you know is a ULID | Value is in Base58 / RFC 4122 but checked with the default Base32 | Pass the matching `Ulid::FORMAT_*` argument. |

## 10. Version Constraints

| Package | Required |
|---|---|
| `symfony/uid` | `^8.1` (for `Uuid47Transformer` and `Ulid::isValid($format)`; v7 default, microsecond precision, and `MockUuidFactory` carried from `^7.4`) |
| `symfony/doctrine-bridge` | `^8.1` (for `UuidType`) |
