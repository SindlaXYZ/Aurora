# Translation Component

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-18 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-translation-improvements
* https://symfony.com/doc/8.1/translation.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

Symfony 8.1 ships five additive changes to the Translation component: a reusable `LocaleFallbackProvider`, env-var support on `framework.enabled_locales`, a fix to `ChoiceType` placeholder translation, native acceptance of XLIFF 2.1 / 2.2, and support for the XLIFF 2.2 PGS module (Plural/Gender/Select).

## 1. What Changed in Symfony 8.1

All changes are **additive** - no breaking changes, no migration forced.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Locale fallback algorithm | Hard-wired inside `Translator`; not reusable | New `Symfony\Component\Translation\LocaleFallbackProvider` - same algorithm, standalone class |
| `framework.enabled_locales` value source | Static `string[]` only | Accepts `%env(...)%` references; empty values filtered automatically |
| `placeholder` translation on `ChoiceType` with `expanded: true` | Used `choice_translation_domain` (broken for `EntityType` where it defaults to `false`) | Uses `translation_domain` (consistent with non-expanded `<select>`) |
| XLIFF format version | 1.2 and 2.0 | 1.2, 2.0, **2.1**, **2.2** |
| XLIFF PGS module (Plural/Gender/Select) | Not understood | Parsed and converted to ICU MessageFormat; results registered in the `+intl-icu` domain |

## 2. `LocaleFallbackProvider` - Reusable Fallback Chain

`Symfony\Component\Translation\LocaleFallbackProvider` exposes the fallback algorithm previously locked inside `Translator`. Use it whenever code OUTSIDE the translator needs the same chain (e.g. choosing which template / asset / DB row to load when the user's locale has no exact match).

```php
use Symfony\Component\Translation\LocaleFallbackProvider;

$provider = new LocaleFallbackProvider(['en']);    // configured fallback locales

$chain = $provider->computeFallbackLocales('es_AR');
// ['es_419', 'es', 'en']
//   ^^^^^^^   ^^   ^^
//   ICU parent  shorter sub-tag  configured fallback
```

The chain is built by (1) following ICU parent mappings, (2) shortening locale sub-tags, (3) appending the configured fallback locales passed to the constructor.

Static validation helper (throws `InvalidArgumentException` on bad input):

```php
LocaleFallbackProvider::validateLocale($locale);
```

**When to use in the Dockraft stub:**
- A service that selects which translated DB column to read (`title_en`, `title_ro`, etc.) from a given user locale.
- A controller that picks which Markdown / Twig fragment to render based on `Accept-Language`.
- Custom `LocaleSwitcher` listeners that need fallback resolution before swapping the request locale.

Do NOT re-implement fallback in user code; always defer to this provider so the behavior matches what the Translator itself uses.

## 3. `framework.enabled_locales` - Environment Variables

`framework.enabled_locales` now accepts `%env(...)%` references directly. Empty values are filtered out, so an unset env var resolves to "no entry" rather than an error.

```yaml
# config/packages/translation.yaml
framework:
    enabled_locales:
        - '%env(LOCALE_1)%'
        - '%env(LOCALE_2)%'
        - '%env(LOCALE_3)%'
        - '%env(LOCALE_4)%'
```

```dotenv
# .env.local (DEV)
LOCALE_1=en
LOCALE_2=ro
LOCALE_3=
LOCALE_4=
```

Result: enabled locales = `['en', 'ro']`. The two empty slots are dropped.

**When to use in the Dockraft stub:**
- Multi-tenant deployment where different installations expose different language subsets.
- DEV vs PROD locale lists driven from `.env.local` without keeping two `translation.yaml` variants.
- Feature-flagged languages (e.g. roll out `fr` only on staging).

The previous static form (`enabled_locales: [en, ro]`) still works unchanged.

## 4. `ChoiceType` Expanded Placeholder Translation Fix

When `ChoiceType` is rendered with `expanded: true` (radios / checkboxes), the `placeholder` label is now translated through the form's `translation_domain` - same as the non-expanded `<select>` rendering.

**Why this matters:** `EntityType` extends `ChoiceType` and defaults `choice_translation_domain` to `false` (entity labels usually come from the DB and must NOT be translated). Pre-8.1, this also disabled placeholder translation, leaving `TranslatableMessage` placeholders untranslated.

```php
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

$form = $this->createForm(EntityType::class, null, [
    'class'              => Country::class,
    'expanded'           => true,
    'placeholder'        => new TranslatableMessage('select_a_country', [], 'forms'),
    'translation_domain' => 'forms',
]);
```

- 8.0: `placeholder` rendered literally as `select_a_country` (bug).
- 8.1: placeholder is translated against the `forms` domain.

**Dockraft stub context:** the stub is API-Platform-first; Symfony Forms are not used for the REST surface. This rule applies if you add a non-API admin UI / settings panel rendered with `form.html.twig`. If your stub stays pure API, this is a passive bug fix you don't have to act on.

## 5. XLIFF 2.1 and 2.2 - Native Acceptance

The XLIFF loader now accepts files declaring `version="2.1"` or `version="2.2"`. The structures of 2.1 and 2.2 are fully compatible with 2.0, so previously-rejected files now load transparently:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0"
    version="2.2" srcLang="en" trgLang="fr">
    <file id="f1">
        <unit id="welcome">
            <segment>
                <source>Welcome</source>
                <target>Bienvenue</target>
            </segment>
        </unit>
    </file>
</xliff>
```

No code change required. Translator vendors that ship 2.1/2.2 files (e.g. CAT tools that auto-upgraded their export format) work out of the box.

## 6. XLIFF 2.2 PGS Module (Plural / Gender / Select)

Symfony 8.1 parses the [PGS module](https://docs.oasis-open.org/xliff/xliff-core/v2.2/xliff-extended-v2.2-part2.html#plural_gender_select_module) and **converts PGS attributes to ICU MessageFormat internally**. Results are registered in the **`+intl-icu`** translation domain, so `trans()` resolves them transparently - no special call site code is needed.

Two attributes drive PGS:

- `pgs:switch` - declares the variable name and switch type (`plural`, `gender`, or `select`). Can combine multiple switches space-separated: `pgs:switch="gender:host_gender plural:guest_count"`.
- `pgs:case` - matches a value for each `<segment>` inside the unit.

Example - plural form for a delete-files message:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0"
    xmlns:pgs="urn:oasis:names:tc:xliff:pgs:1.0"
    version="2.2" srcLang="en" trgLang="fr">
    <file id="f1">
        <unit id="file_deleted" pgs:switch="plural:file_count">
            <segment id="s1" pgs:case="0">
                <source>You deleted no file.</source>
                <target>Vous n'avez supprimé aucun fichier.</target>
            </segment>
            <segment id="s2" pgs:case="1">
                <source>You deleted one file.</source>
                <target>Vous avez supprimé un fichier.</target>
            </segment>
            <segment id="s3" pgs:case="other">
                <source>You deleted <ph id="1" disp="file_count"/> files.</source>
                <target>Vous avez supprimé <ph id="1" disp="file_count"/> fichiers.</target>
            </segment>
        </unit>
    </file>
</xliff>
```

When multiple PGS switches are combined in the same unit, Symfony automatically nests the generated ICU structures (gender outer, plural inner, etc.). Consumers do nothing special - `trans('file_deleted', ['file_count' => 5], 'messages+intl-icu')` returns the right string.

**File-naming reminder:** to make a unit ICU-resolved, the catalogue's domain must end in `+intl-icu` (e.g. `messages.fr.xliff` resolved into the `messages` domain → NO ICU; `messages+intl-icu.fr.xliff` → ICU enabled). PGS units only make sense in `+intl-icu` files.

## 7. Anti-patterns

- **Re-implementing the locale fallback chain** in user code instead of calling `LocaleFallbackProvider::computeFallbackLocales()`. The algorithm has corner cases (ICU parents, region sub-tags) you do NOT want to maintain.
- **Putting non-trans-safe values in `%env(LOCALE_*)%`** without filtering - if an env var contains whitespace or invalid characters, the translator will fail at boot. Validate at deploy time or wrap with `%env(trim:default:LOCALE_1)%` style processors.
- **Mixing `placeholder` with a string `TranslatableMessage` and forgetting `translation_domain`** - without the domain, the placeholder falls back to the default `messages` domain. Set both explicitly.
- **Hand-writing ICU `{count, plural, ...}` strings in XLIFF 2.0** when your CAT tool can emit XLIFF 2.2 PGS - let the tool handle the syntax, let Symfony convert.
- **Forgetting the `+intl-icu` suffix** on catalogue files containing PGS units - without the suffix, the resulting ICU MessageFormat is treated as a plain string and curly braces leak to the consumer.
- **Storing 8.0 fallback computations** in a cache without re-validating on 8.1 - if you previously cached locale chains, drop the cache after upgrade so any algorithmic improvements take effect.

## 8. Dockraft Stub Applicability

The Dockraft Symfony stub is an API-Platform-first REST API with no shipped translations directory. Below is a per-feature applicability map:

| Feature | Stub usage |
|---|---|
| `LocaleFallbackProvider` | Use when adding multilingual entity fields (e.g. `title_en` / `title_ro` columns) and a service needs to pick the right column for the request locale |
| `enabled_locales` env vars | Adopt when the same image is deployed to tenants with different language subsets - drives the locale list from `.env.local` instead of YAML |
| `ChoiceType` placeholder fix | Passive - only matters if a non-API admin UI is added with Symfony Forms |
| XLIFF 2.1 / 2.2 acceptance | Passive - no action; new translation files just work |
| XLIFF 2.2 PGS module | Use `messages+intl-icu.{locale}.xliff` files containing PGS units when translating pluralized validation messages or response strings |

## 9. Migration Notes (8.0 → 8.1)

No required migration. Adopt incrementally:

1. Replace hand-rolled fallback logic with `LocaleFallbackProvider::computeFallbackLocales()`.
2. Move static `enabled_locales` lists to env vars when per-env / per-tenant variation is needed.
3. Stop hand-writing ICU `{count, plural, ...}` strings in XLIFF if your translation pipeline outputs 2.2 PGS - Symfony now does the conversion for you.
4. If you previously worked around the `expanded ChoiceType` placeholder bug (manually passing `choice_translation_domain` everywhere), remove the workaround after upgrading.
