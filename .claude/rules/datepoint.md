# DatePoint Doctrine Types

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/components/clock.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

`Symfony\Component\Clock\DatePoint` is a Clock-component-aware immutable date/time value object - a small wrapper on top of `\DateTimeImmutable` with a tighter API (no string-based construction surprises, no implicit timezone fallbacks). It is usable seamlessly anywhere a `\DateTimeImmutable` or `\DateTimeInterface` is expected.

The Clock component has **no entries in `CHANGELOG-8.1.md`** - `DatePoint` and its three Doctrine types are unchanged on 8.1 from the 7.3 / 7.4 versions that introduced them. This rule is carried over to the v8.1 stub verbatim in substance, with the Doctrine type mapping aligned to the 8.1 documentation and the version constraints set to the 8.1-compatible range.

Three Doctrine types ship with `symfony/doctrine-bridge` and are registered in `config/packages/doctrine.yaml`. The 8.1 docs name the underlying Doctrine base type and the DBAL type class for each:

| Type | Extends Doctrine type | DBAL type class | Use for |
|---|---|---|---|
| `date_point` | `datetime_immutable` | `DatePointType` | `createdAt`, `publishedAt`, `expiresAt` (date + time) |
| `day_point` | `date_immutable` | `DayPointType` | `birthDate`, `holidayDate` (date only, no time) |
| `time_point` | `time_immutable` | `TimePointType` | `dailyCutoffTime`, `opensAt` (time only, no date) |

## 1. Why Prefer `DatePoint` over `\DateTimeImmutable`

- **Clock-aware:** in tests, `MockClock::now()` and the `Symfony\Component\Clock\now()` helper return a `DatePoint`. Production code that types against `DatePoint` accepts the mock natively without bridges.
- **Stricter API:** `new DatePoint('now')` requires explicit input formats; no silent surprises from `\DateTime`'s loose parsing. `DatePoint::createFromTimestamp(1129645656)` and `new DatePoint('+1 month', reference: $referenceDate)` are the explicit constructors.
- **Pairs with the 8.1 Clock-aware validators.** `GreaterThan`, `LessThan`, `Range`, etc. resolve relative date strings (`-30 days`, `today`) against the Clock service in 8.1 - typing the property as `DatePoint` keeps validation and persistence consistent and deterministic in tests. See `.claude/rules/validator.md` (section 14, Clock-aware comparison and range validators).

## 2. Entity Usage

```php
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\DatePoint;

#[ORM\Entity]
class Article
{
    #[ORM\Column(type: 'date_point')]
    private DatePoint $createdAt;

    #[ORM\Column(type: 'day_point', nullable: true)]
    private ?DatePoint $publishedOn = null;

    #[ORM\Column(type: 'time_point', nullable: true)]
    private ?DatePoint $dailyDigestAt = null;
}
```

## 3. Rules

1. **Always type the property as `DatePoint`, never as `\DateTimeImmutable`.** Even though `DatePoint` is a `\DateTimeImmutable` wrapper, typing as the parent forgoes the static-analysis benefit and lets plain `\DateTimeImmutable`-only values slip in.
2. **Construct via the Clock service in services.** Inject `Symfony\Component\Clock\ClockInterface` and call `$this->clock->now()` instead of `new DatePoint()` directly. In tests, replace the clock with `MockClock`.
3. **Do NOT mix Doctrine `datetime` / `date` / `time` types with the `*_point` variants on the same property.** Renaming the type on an existing column requires a migration - write one explicitly via `doctrine:migrations:diff` and verify the SQL before running it on production data.
4. **Repositories and DQL: parameters accept both `\DateTimeImmutable` and `DatePoint`.** No conversion needed at query time; Doctrine's DBAL handles the binding through the registered type.
5. **JSON serialization is identical to `\DateTimeImmutable`.** No surprises in API Platform output - but the canonical `format` for the property is still controlled by the API Platform / Serializer configuration.

## 4. Migration from `\DateTimeImmutable`

For each property migrated:

1. Change the type-hint: `private \DateTimeImmutable $foo;` -> `private DatePoint $foo;`.
2. Change the column: `#[ORM\Column]` -> `#[ORM\Column(type: 'date_point')]`.
3. Run `bin/console doctrine:migrations:diff` - should produce a NO-OP for plain `date_point` (the underlying type is `datetime_immutable` either way). For `day_point` / `time_point` on a column previously typed `datetime`, the migration alters the column type - review before running.
4. Update services that construct the value to use `ClockInterface`.

## 5. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Could not convert PHP value of type ... to type date_point` | Property still typed as `\DateTimeImmutable` and not a real `DatePoint` instance | Type the property as `DatePoint` and construct via `$clock->now()`. |
| `Unknown Doctrine type "date_point"` | `types.date_point` missing from `doctrine.yaml` | Re-apply the `types:` block under `doctrine.dbal:` from `doctrine.yaml.example`. |
| `Cannot use object of type DateTimeImmutable as DatePoint` in a setter | A caller passed a raw `\DateTimeImmutable` | Wrap with `DatePoint::createFromInterface($dt)` at the caller, OR change the setter to accept `\DateTimeImmutable` and convert inside. |

## 6. Version Constraints

| Package | Required |
|---|---|
| `symfony/clock` | `^8.1` (provides `DatePoint`; the class is unchanged from the 7.3 introduction) |
| `symfony/doctrine-bridge` | `^8.1` (registers `date_point`, `day_point`, `time_point`) |
| `doctrine/orm` | `^3.0` |
