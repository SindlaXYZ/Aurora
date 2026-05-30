# Translator - Global Translation Parameters

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/translation.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

`translator.globals` are translation placeholders defined once and reused across every translation message without explicit passing. The feature was introduced in the 7.3 line and is **still present and valid on 8.1** (verified against the 8.1 translation docs).

**Scope - this file vs `translation.md`.** This rule covers ONLY global translation parameters. The 8.1 Translation component additions (the reusable `LocaleFallbackProvider`, env-var support on `framework.enabled_locales`, the `ChoiceType` expanded-placeholder fix, XLIFF 2.1 / 2.2 acceptance, and the XLIFF 2.2 PGS module) are documented in `.claude/rules/translation.md`. Do NOT duplicate them here; for anything other than globals, see that file.

## 1. When to Use

Reach for global parameters when the SAME value appears in many translation strings:

- Application name, version, support email
- Public URLs (homepage, terms-of-service)
- Tenant / brand name in white-label apps

Skip them for one-off values - passing parameters explicitly is clearer when they appear in a single message.

## 2. Configuration

Globals live under `framework.translator.globals` in `translator.yaml`. A global value is either a literal string or a map (`message` / `parameters` / `domain`) that is itself resolved as a `TranslatableMessage`:

```yaml
# config/packages/translator.yaml
framework:
    translator:
        default_path: '%kernel.project_dir%/translations'
        globals:
            '{app_name}': 'Dockraft Admin'
            '{app_version}': '1.2.3'
            '{support_email}': 'support@example.com'
            # A global parameter that is itself a TranslatableMessage:
            '{homepage}':
                message: 'homepage_url'
                parameters: { scheme: 'https://' }
                domain: 'global'
```

Placeholder syntax: the curly-brace form `{name}` is the canonical convention. The legacy percent form is also accepted, but the `%` characters must be escaped (`'%%app_name%%'`) because `%...%` is the container-parameter syntax in YAML.

## 3. Usage

```twig
{{ 'Welcome to {app_name}'|trans }}
{{ 'Application version: {app_version}'|trans }}

{# Explicit parameters override globals: #}
{{ 'Package version: {app_version}'|trans({'{app_version}': '2.3.4'}) }}
```

```php
$message = $translator->trans('Welcome to {app_name}');
```

## 4. Rules

1. **Curly-brace placeholders for new globals.** The `{name}` form is the canonical convention. Use the escaped `%%name%%` form only when a message already uses percent placeholders you cannot change.
2. **Globals are visible to ICU messages too.** They participate in plural / select rules - useful for "We have {count, plural, one {1 {item_name}} other {# {item_name}s}}". (ICU resolution still requires the `+intl-icu` domain suffix - see `translation.md` section 6.)
3. **Keep globals SHORT and STABLE.** A global is for values that change rarely (release cycle, branding). Putting per-request data (user name, locale) in globals defeats the purpose - pass them explicitly.
4. **One source of truth.** A global parameter MUST be defined in `translator.yaml`, not scattered across `*.<locale>.yaml` files. The profiler displays globals - use it to verify.
5. **Avoid for security-sensitive content.** Globals are applied verbatim; the translator does NOT escape HTML in them. If the value can contain markup, ensure the consuming context already escapes it (Twig's auto-escape covers HTML output; raw `printf`-style logs do not).

## 5. Testing

```php
$translator = self::getContainer()->get(TranslatorInterface::class);

// Globals are automatically available in tests if translator.yaml is loaded.
self::assertSame('Welcome to Dockraft Admin', $translator->trans('Welcome to {app_name}'));

// Override one global per call:
self::assertSame('Welcome to TempBrand', $translator->trans('Welcome to {app_name}', ['{app_name}' => 'TempBrand']));
```

## 6. Common Errors

| Error | Cause | Fix |
|---|---|---|
| Placeholder appears literal in output (`Welcome to {app_name}`) | Wrong placeholder syntax in the message | Match the global key exactly: `{app_name}` includes the braces. |
| `Environment variable / parameter "app_name" not found` at boot | Percent-form global written without escaping the `%` | Escape it as `'%%app_name%%'`, or switch to the `{app_name}` brace form. |
| Global value not applied in profiler | Cache stale after editing `translator.yaml` | `bin/console cache:clear`. |
| `Cannot resolve global {homepage}` | TranslatableMessage form references a non-existent translation key | Verify `homepage_url` exists in `translations/global.<locale>.yaml`. |

## 7. Version Constraints

| Package | Required |
|---|---|
| `symfony/translation` | `^8.1` (the `translator.globals` feature is unchanged from the `^7.3` introduction) |
