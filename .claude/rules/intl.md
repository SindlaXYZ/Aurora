# Intl - Currency Filtering by Legal Tender

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/components/intl.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

Symfony filters out legacy / non-legal-tender currencies by default in `CurrencyType` form widgets, and the `Currencies` class exposes query helpers for country / date-scoped lookups. These capabilities were introduced in the 7.4 line.

The Intl component has **no entries in `CHANGELOG-8.1.md`** - the currency-filtering API is unchanged on 8.1 from the 7.4 version that introduced it. This rule is carried over to the v8.1 stub with the `Currencies` examples aligned to the 8.1 documentation and the version constraints set to the 8.1-compatible range; the behavior described is identical.

## 1. Why It Matters

The full ICU currency list contains ~290 entries, of which ~80 are still legal tender. Older Symfony versions exposed all of them in dropdowns - users had to scroll past dead currencies (ESP, DEM, BEF) to find EUR. The filtering hides legacy entries by default.

## 2. `Currencies` Helpers

The 8.1 docs document these query helpers on `Symfony\Component\Intl\Currencies`. The date-scoped filters use named arguments (`legalTender`, `active`, `date`):

```php
use Symfony\Component\Intl\Currencies;

// Active legal tender for a country (current date):
$french = Currencies::forCountry('FR');                  // ['EUR']
$swiss  = Currencies::forCountry('CH');                  // ['CHF']

// All currencies valid for a country on a historical date:
$spain1982 = Currencies::forCountry(
    'ES',
    legalTender: null,
    active:      true,
    date:        new \DateTimeImmutable('1982-01-01'),
);

// Country-level validation:
$ok = Currencies::isValidInCountry('CH', 'CHF');         // true
$ok = Currencies::isValidInCountry('FR', 'USD');         // false

// Validity in ANY country (same named-argument filters):
$isGlobal = Currencies::isValidInAnyCountry(
    'USD',
    legalTender: true,
    active:      true,
    date:        new \DateTimeImmutable('2005-01-01'),
);
```

## 3. `CurrencyType` Form Options

When the project renders a `CurrencyType` dropdown (Symfony Forms), the filtering is controlled by these options. They belong to the Form component, not the Intl component, so they do not appear on the Intl docs page - their behavior is unchanged from 7.4.

| Option | Default | Behaviour |
|---|---|---|
| `legal_tender` | `true` | `true` -> only legal tender, `false` -> only non-legal, `null` -> all |
| `active_at` | unset | When set, restricts to currencies active at that date |
| `not_active_at` | unset | When set, restricts to currencies INACTIVE at that date |

```php
$builder->add('currency', CurrencyType::class, [
    // Default: only currently legal currencies. No code needed.
]);

$builder->add('historicalCurrency', CurrencyType::class, [
    'legal_tender' => null,
    'active_at'    => new \DateTimeImmutable('2007-01-15'),
]);
```

**Dockraft stub context:** the stub is API-Platform-first; Symfony Forms are not used for the REST surface. The `CurrencyType` options apply only if you add a non-API admin UI / settings panel. The `Currencies` class helpers (section 2) are useful regardless - validating a persisted ISO 4217 code against a country in a service or a custom validator constraint.

## 4. Rules

1. **Default suffices for 95% of forms.** Do NOT override `legal_tender: null` unless the form is specifically about historical / collectible currencies.
2. **Pair `active_at` with `legal_tender: null`.** Otherwise you constrain to "legal AND active at date" which excludes legitimate historical legal-tender combinations.
3. **Store ISO 4217 alpha codes, not the localized label.** `EUR`, not `Euro` / `Euro (€)`. The form returns the alpha code; persist it as a 3-char column.
4. **Validate persisted codes against the country.** Use `Currencies::isValidInCountry()` in a validator constraint when the entity has both a country code and a currency column.

## 5. Common Errors

| Error | Cause | Fix |
|---|---|---|
| Dropdown is empty for an exotic country | All currencies for that country are non-legal-tender | Set `legal_tender: null` deliberately. |
| Historical reports show "Unknown currency" | The ICU bundle lacks the entry OR the locale is wrong | Use the ALPHA code from the form value, not the symbol. |
| `\TypeError` on `Currencies::forCountry(...)` | Positional arguments passed for the date filters | Always use named arguments: `legalTender:`, `active:`, `date:`. |

## 6. Version Constraints

| Package | Required |
|---|---|
| `symfony/intl` | `^8.1` (the `Currencies` helpers and `CurrencyType` filtering options are unchanged from the `^7.4` introduction) |
