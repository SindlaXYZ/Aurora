# Twig 3.23+ / 3.25+ / 3.26+ / 3.27+ Rules

| Version | Created    | Updated    |
|---------|------------|------------|
| 3.27.1  | 2026-05-17 | 2026-06-01 |

**Sources:**
* https://symfony.com/blog/twig-3-27-1-released
* https://symfony.com/blog/twig-3-27-0-released
* https://symfony.com/blog/twig-3-26-0-released
* https://symfony.com/blog/twig-3-25-0-released
* https://symfony.com/blog/twig-3-23-introducing-new-operators-and-destructuring-support
* https://symfony.com/blog/new-in-symfony-7-3-twig-extension-attributes
* https://github.com/twigphp/Twig/blob/3.x/CHANGELOG
* https://twig.symfony.com/doc/3.x/

Twig is versioned independently of Symfony. The rules below apply whenever `twig/twig` is at `^3.23` (sections 6, 8-9), `^3.25` (sections 1-5), `^3.26` (section 7), or `^3.27` (section 8). The current release is **3.27.1** (May 2026, a bugfix patch over 3.27.0); the rule recommends pinning to it for the full sandbox-security posture. The `twig.safe_class` service tag (section 4) requires Symfony 8.1 - which the v8.1 stub runs - so it is simply available here.

**Two recent security-focused releases.** 3.26.0 (May 2026) bundles 13 sandbox / XSS fixes (section 7). 3.27.0 (May 2026) adds 5 more sandbox fixes plus an opt-in strict `SecurityPolicy` mode and deprecates `SourcePolicyInterface` (section 8). 3.27.1 (May 2026) is a bugfix patch that fixes two regressions introduced by the 3.27.0 sandbox hardening (section 8.6). Upgrade is REQUIRED for any project that renders untrusted templates through the sandbox; sections 7-8 also list the behavior changes that affect non-sandbox code.

## 1. Sandbox-Aware Filters, Functions, and Tests - Use `needs_is_sandboxed`

When a custom filter, function, or test must adapt its behavior depending on whether the current template is sandboxed, declare the option `needs_is_sandboxed` (or the named argument `needsIsSandboxed` on the PHP 8 attributes). Twig will then pass the current sandbox state as a **boolean first argument** to the callable.

Do NOT roll your own sandbox detection (inspecting `\Twig\Extension\SandboxExtension`, walking the call stack, reading globals). The flag exposed by `needs_is_sandboxed` is the only API that correctly accounts for both the global sandbox state and any configured `SecurityPolicy`.

```php
use Twig\TwigFilter;

$filter = new TwigFilter('rot13', function (bool $sandboxed, string $text): string {
    if (true === $sandboxed) {
        // adjust behavior when running in a sandboxed template
        // e.g. strip dangerous characters, refuse external lookups, etc.
    }

    return str_rot13($text);
}, ['needs_is_sandboxed' => true]);
```

Attribute form (preferred for service-tagged extensions under `App\Twig\`):

```php
use Twig\Attribute\AsTwigFilter;

final class Rot13Extension
{
    #[AsTwigFilter('rot13', needsIsSandboxed: true)]
    public function rot13(bool $sandboxed, string $text): string
    {
        // ...
        return str_rot13($text);
    }
}
```

The same option exists on `TwigFunction`/`#[AsTwigFunction]` and `TwigTest`/`#[AsTwigTest]`.

## 2. Embeds Are Deterministic - Do NOT Cache-Bust the Twig Cache

Since 3.25.0, `{% embed %}` produces stable compiled output across runs. Pre-compiled Twig builds are now reproducible.

Practical consequence: deployment scripts and CI builds that previously deleted `var/cache/*/twig/` "to force re-compilation because embeds change every build" are obsolete. Remove any such cleanup step - it just inflates cold-start latency. Standard `cache:clear` during deploys is sufficient.

## 3. Overriding `EscaperRuntime` - Use a Runtime Loader, Not an Extension Swap

`EscaperRuntime` is substitutable via any runtime loader (since 3.25.0). When project code needs custom escaping (e.g. additional safe-class registration, custom strategies, or an alternative HTML escaper), register a replacement through a runtime loader. Do NOT replace `EscaperExtension` itself or monkey-patch the global `Environment`.

```php
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

$twig = new Environment(new ArrayLoader());
$twig->addRuntimeLoader(new FactoryRuntimeLoader([
    EscaperRuntime::class => static fn (): EscaperRuntime => new App\Twig\Runtime\AppEscaperRuntime(),
]));
```

In Symfony, register the custom runtime as `twig.runtime.escaper` so the framework's `ContainerRuntimeLoader` picks it up.

## 4. Marking PHP Classes as Safe - Use `twig.safe_class`

When a value object's `__toString()` produces pre-escaped HTML and should NOT be re-escaped by Twig, mark the class as safe via the **`twig.safe_class`** resource tag instead of calling `$escaperRuntime->addSafeClass()` from a `boot()` method, a compiler pass, or an event subscriber.

```php
// config/services.php
use App\ValueObject\InvoiceNumber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(InvoiceNumber::class)
        ->resourceTag('twig.safe_class', ['strategy' => 'html']);
};
```

- `strategy` accepts the same values as Twig's escaping contexts (`'html'`, `'js'`, `'css'`, `'url'`, `'html_attr'`).
- This tag requires **Symfony 8.1** (`symfony/twig-bundle ^8.1`), which the v8.1 stub runs - it is available out of the box, no fallback needed. (On Symfony 8.0 the only way was the runtime-loader pattern from section 3 with `$runtime->addSafeClass(...)`.)

## 5. Assignment + Destructuring (Twig 3.23+)

Twig 3.23 added several operators that bring template syntax closer to modern PHP / JS. Use them deliberately - they make templates LESS scannable when overused.

### 5.1 Assignment Operator (`=`)

```twig
{{ b = 1 + 3 }}                 {# inline assignment, prints "4" #}
{% do a = b = 'foo' %}          {# chained, right-associative - both a and b become 'foo' #}
```

Rule: prefer `{% set name = value %}` for readability. Inline `=` is acceptable ONLY when the result is also rendered in the same expression (rare).

### 5.2 Sequence Destructuring

```twig
{% do [first, last] = ['Fabien', 'Potencier'] %}
{{ first }} {{ last }}

{# Skip slots: #}
{% do [, last] = ['Fabien', 'Potencier'] %}
```

Use when iterating a tuple-shaped value (e.g. `[label, count]` pairs).

### 5.3 Object / Mapping Destructuring

```twig
{% do {name, email} = user %}
{{ name }}
```

The mapping form picks properties by name. Rule: keep destructured names to 3-4 max - beyond that, `user.name` / `user.email` reads better than a destructure block.

### 5.4 Null-Safe Operator (`?.`)

```twig
{{ user?.name }}
{{ user?.address?.city }}
```

Returns `null` instead of throwing when the left operand is `null`. Replaces verbose `{% if user is not null and user.address is not null %}...{% endif %}` patterns.

Rule: chain at most 3 levels deep. Beyond that, the data shape is the problem - fix it upstream rather than papering over with `?.`.

### 5.5 Strict Comparison Operators (`===` and `!==`)

```twig
{% if value === null %}
{% if status !== 'pending' %}
```

Equivalent to the existing `same as` / `not same as` tests but matches PHP's idiom. Rule: **prefer `===` / `!==` in new templates** for parity with the controller logic; reserve `==` / `!=` for cases where Twig's loose comparison is intentional.

## 6. Custom Extensions via PHP Attributes (Symfony 7.3+ / Twig 3.x)

`#[AsTwigFilter]`, `#[AsTwigFunction]`, and `#[AsTwigTest]` declare a method as a Twig callable without extending `AbstractExtension` or implementing `getFilters()` / `getFunctions()`.

### 6.1 Filter

```php
namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class FormattingExtension
{
    #[AsTwigFilter('product_number')]
    public function formatProductNumber(string $number): string
    {
        return strtoupper(str_pad($number, 10, '0', STR_PAD_LEFT));
    }
}
```

Use in templates: `{{ '12345'|product_number }}`.

### 6.2 Function

```php
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

final class TextExtension
{
    #[AsTwigFunction('lipsum')]
    public function lipsum(Environment $env, int $count): string
    {
        // ...
    }
}
```

When the first parameter type-hints `Twig\Environment` (or `Context`), Twig auto-detects `needsEnvironment` (or `needsContext`).

### 6.3 Test

```php
use Twig\Attribute\AsTwigTest;

final class DomainTests
{
    #[AsTwigTest('overdue')]
    public function isOverdue(Invoice $invoice): bool
    {
        return $invoice->dueDate < new \DateTimeImmutable();
    }
}
```

Use in templates: `{% if invoice is overdue %}`.

### 6.4 Combining with Sandbox-Awareness

The attributes accept the same options as the legacy `TwigFilter` / `TwigFunction` constructors - `needsIsSandboxed`, `needsEnvironment`, `needsContext`, `isSafe`, `preEscape`, etc. See section 1 for the sandbox pattern.

### 6.5 Rules

1. **One concern per extension class.** Group related callables (`FormattingExtension`, `DomainTests`, `RoutingExtension`). Avoid mega-extensions with 40 unrelated methods.
2. **Methods are LAZY-LOADED by default.** The class is constructed only when one of its callables is invoked - no boot-time cost for unused extensions.
3. **Public methods only.** Private / protected methods with the attribute are silently ignored.
4. **Live under `App\Twig\` (autoconfigured).** Symfony's container auto-tags any class in this namespace as a Twig extension when it has at least one `#[AsTwig*]` attribute.
5. **No `getFilters()` / `getFunctions()` / `getTests()` boilerplate.** When using attributes, those methods must NOT exist on the class - mixing the two styles in one class is a maintenance hazard.

## 7. Twig 3.26.0 - Sandbox Hardening + HTML Pre-Escape Changes

Twig 3.26.0 (May 2026) is a pure security release: no new tags, filters, functions, or operators. It contains 13 sandbox / XSS fixes and one new public API. Two changes affect projects even when the sandbox is OFF; the rest apply only to sandboxed code paths.

### 7.1 Mandatory upgrade matrix

| Project profile                                         | 3.26 upgrade urgency                |
|---------------------------------------------------------|-------------------------------------|
| Renders ANY untrusted template via `SandboxExtension`   | CRITICAL - upgrade immediately      |
| Uses `spaceless`, `inline_css`, or `inky_to_html` filters | HIGH - verify HTML output unchanged (see 7.2) |
| Pure first-party templates, no sandbox                  | MEDIUM - upgrade on next cycle for defense in depth |

### 7.2 HTML pre-escape change on `spaceless` / `inline_css` / `inky_to_html`

These three filters now pre-escape HTML input before processing. Templates that intentionally fed unescaped HTML through them and relied on the OUTPUT being raw HTML will see escaped entities (`&lt;` instead of `<`) in 3.26+.

```twig
{# Before 3.26: rendered <strong>Hi</strong> #}
{# Since 3.26:  renders &lt;strong&gt;Hi&lt;/strong&gt; (unless `|raw` is added) #}
{{ user_supplied_html|spaceless }}
```

Rule: audit every call site of these three filters after the upgrade. The correct fix is one of:

1. Mark the input safe BEFORE the filter, only when you genuinely trust the source: `{{ trusted_html|raw|spaceless }}`.
2. Wrap the filter chain in `|raw` AFTER, when the source is also trusted: `{{ ('<p>' ~ name ~ '</p>')|spaceless|raw }}`.
3. Leave the new escaping in place when the original behavior was a latent XSS bug.

Default to option 3 - the pre-escape change is closing a real XSS class; only fall back to `|raw` when the input is provably trusted.

### 7.3 `is_safe` annotation adjusted on HTML-emitting filters in `twig/extra-bundle`

The `is_safe: ['html']` flag was removed from several `twig/extra-*` filters that returned attacker-controlled output without escaping. After upgrade, the output of those filters is escaped by Twig's auto-escaper. Same audit as 7.2 applies if a template relied on the implicit `is_safe` to skip escaping.

If a project pinned `twig/extra-bundle` to `< 3.26`, the `composer require twig/twig:^3.27` upgrade will not auto-bump it - bump the extra bundle explicitly: `composer require twig/extra-bundle:^3.27`.

### 7.4 New interface - `Twig\Node\CoercesChildrenToStringInterface`

Custom `Node` subclasses (rare - only meta-frameworks that compile custom tags) can implement this interface to declare which child nodes will be coerced to string at runtime. The sandbox uses this to wrap those children with a `__toString` check, blocking the bypasses fixed in 3.26.

```php
use Twig\Node\CoercesChildrenToStringInterface;
use Twig\Node\Node;

final class MyCustomNode extends Node implements CoercesChildrenToStringInterface
{
    public function getChildrenCoercedToString(): array
    {
        // Return the keys of $this->nodes children that will be (string)-cast at runtime.
        return ['content', 'label'];
    }
}
```

Rule: implement this interface on EVERY custom Node that does string concatenation / `(string)` casting of child node output in its `compile()` method. Without it, the sandbox cannot enforce `__toString` safety against objects rendered through the custom tag.

The vast majority of projects do not write custom `Node` classes - this interface is relevant only to bundle authors and template-engine tooling.

### 7.5 Sandbox bypass fixes - reasons to re-test sandboxed templates

3.26 closes 7 distinct sandbox bypass classes. If a project deliberately constructed sandbox tests around exploits in `< 3.26`, those tests will now fail (the exploit no longer works). Fix forward; do NOT pin Twig back. The patched bypasses:

| # | Bypass surface                                              | Fix |
|---|-------------------------------------------------------------|-----|
| 1 | `_self` / import macro reference -> PHP code injection      | Compile-time validation of macro names |
| 2 | `{% use %}` template name -> PHP code injection             | Compile-time validation of `use` argument |
| 3 | `{% sandbox %}` tag including a preloaded template          | Sandbox flag propagated to preloaded templates |
| 4 | `column` filter argument                                    | Argument routed through sandbox policy check |
| 5 | Object destructuring assignment (3.23+ syntax)              | Destructured property reads checked against sandbox |
| 6 | `__toString()` coercion bypasses                            | Sandbox now wraps every implicit string coercion (driven by 7.4 interface) |
| 7 | `checkArrow` source-policy lookup                           | `Source` object propagated so the source-policy hook can decide per-template |

Test action: in projects with a custom source-policy implementation, run `bin/phpunit` against the sandbox test suite after upgrade. Any test that asserts "this exploit succeeds" must be inverted to assert "this exploit is blocked". (Note: the source-policy mechanism itself is deprecated in 3.27 - see section 8.3.)

### 7.6 Hardened defense in depth

- `Compiler::string()` now encodes single quotes as `\x27`. Code that introspects compiled templates by string-matching against the literal `\'` will break - use `Twig\Source` or `Twig\TemplateWrapper` APIs instead of regex over compiled output.
- `IntlDateFormatter` / `NumberFormatter` memoization is now bounded - prevents long-running workers from OOMing on attacker-controlled locale strings.
- `HtmlDumper` (profiler) escapes template / profile names - the profiler panel no longer renders attacker-controlled template names as raw HTML.

### 7.7 Common errors after upgrading to 3.26

| Symptom                                                       | Cause                                                  | Fix |
|---------------------------------------------------------------|--------------------------------------------------------|-----|
| HTML entities visible in rendered page after `spaceless` etc. | Pre-escape change in 7.2                               | Add `|raw` to a trusted source OR fix the underlying XSS. |
| `Twig\Sandbox\SecurityError` in `{% use %}` / `_self` paths   | Compile-time validation rejects dynamic / suspicious names | Replace dynamic `{% use %}` with static template names. |
| Sandbox unit test failing: "expected exploit to succeed"      | The exploit is now blocked (correct behavior)          | Invert the assertion (`expectException(SecurityError::class)`). |
| Custom tag fails sandbox check on object rendering            | Custom `Node` does string coercion without declaring it | Implement `CoercesChildrenToStringInterface` (see 7.4). |

## 8. Twig 3.27.0 - More Sandbox Fixes + Strict SecurityPolicy + `SourcePolicyInterface` Deprecation

Twig 3.27.0 (released 27 May 2026) is a security release: 5 sandbox fixes (1 low, 4 medium), an opt-in strict mode for `SecurityPolicy` that previews the Twig 4.0 sandbox defaults, and the deprecation of `SourcePolicyInterface`. No new template-language features. (The current release is 3.27.1, a bugfix patch over 3.27.0 - see section 8.6.)

### 8.1 The 5 sandbox fixes

All sandbox-only. Re-test sandboxed templates after upgrade (same discipline as 7.5):

| CVE | Surface | Fix |
|---|---|---|
| CVE-2026-46636 | Sandbox filter/tag/function allow-list bypass when sandbox state changes between renders | State no longer leaks across renders |
| CVE-2026-48806 | `__toString` policy bypass via dynamic mapping keys | Dynamic keys routed through the policy check |
| CVE-2026-48807 | `__toString` bypasses via `Traversable` in `join` / `replace` filters and the `in` / `not in` operators | Traversable coercion checked against the policy |
| CVE-2026-48808 | `column` filter bypass under the source-policy mechanism | Argument routed through the policy check |
| CVE-2026-48805 | Bypass in deprecated internal wrappers | Wrapper hardened |

### 8.2 Opt-in Strict `SecurityPolicy` Mode - `setStrict(true)`

Twig 4.0 will require EVERY tag and function used in a sandboxed template to be explicitly listed in the `SecurityPolicy` allow-lists. 3.27 lets a project adopt that behavior early via `SecurityPolicy::setStrict(true)`:

```php
use Twig\Sandbox\SecurityPolicy;

$policy = new SecurityPolicy(
    allowedTags: ['extends', 'if', 'for'],
    allowedFilters: ['escape', 'upper'],
    allowedFunctions: ['parent', 'block'],
);
$policy->setStrict(true);
```

"With strict mode on, every tag and every function must appear in the relevant allow-list to be usable." Without strict mode, `extends` / `use` and `parent` / `block` / `attribute` are still implicitly allowed (and now emit deprecations - see 8.3).

Rule: turn strict mode ON in any project that renders untrusted templates through `SandboxExtension`. It surfaces the missing allow-list entries now, on 3.27, instead of breaking when 4.0 makes the strict behavior mandatory. Add `extends`, `use`, `parent`, `block`, and `attribute` to the allow-lists explicitly if your sandboxed templates use them.

### 8.3 `SourcePolicyInterface` Deprecated - No Replacement

`Twig\Sandbox\SourcePolicyInterface` is **deprecated in 3.27 with no replacement.** The recommended way to render templates written by untrusted authors is to **point a dedicated sandboxed `Environment` at a loader that restricts what those templates can see**, rather than toggling sandbox state per source from within a shared environment.

Consequences for this stub:

- Projects with a custom `SourcePolicyInterface` implementation must migrate to a dedicated sandboxed `Environment` + a restrictive loader before Twig 4.0. The per-template `Source`-driven decisions described in row 7 of section 7.5 are the use case this deprecation retires.
- New code MUST NOT introduce a `SourcePolicyInterface` implementation. Isolate untrusted templates in their own `Environment` with `SandboxExtension` + a strict `SecurityPolicy` (8.2) and a loader scoped to only the templates those authors may reference.

### 8.4 Implicit-Allow Deprecation for `extends`/`use` + `parent`/`block`/`attribute`

In a sandbox, the `extends` and `use` tags plus the `parent`, `block`, and `attribute` functions have always been implicitly allowed regardless of the `SecurityPolicy`. Twig 3.12 began emitting a deprecation for the two tags; 3.27 extends it to the three functions, because 4.0 will require all of them to be listed explicitly.

Rule: when configuring a `SecurityPolicy` for sandboxed templates, add `extends` and `use` to `allowedTags` and `parent`, `block`, `attribute` to `allowedFunctions` if the templates use them. Doing so now clears the deprecation and is forward-compatible with 4.0 / strict mode (8.2).

### 8.5 Common errors after upgrading to 3.27

| Symptom | Cause | Fix |
|---|---|---|
| Deprecation: implicit allow of `extends` / `use` / `parent` / `block` / `attribute` in a sandbox | 3.27 deprecates the implicit allow-listing (8.4) | Add them explicitly to the `SecurityPolicy` allow-lists. |
| Deprecation: `SourcePolicyInterface` is deprecated | Project implements the now-deprecated interface (8.3) | Migrate untrusted rendering to a dedicated sandboxed `Environment` + restrictive loader. |
| Sandbox template fails with `SecurityError` after enabling `setStrict(true)` | Strict mode requires every tag/function in the allow-list (8.2) | Add the missing tags/functions to `allowedTags` / `allowedFunctions`. |
| Sandbox unit test failing: "expected exploit to succeed" | One of the 5 fixes in 8.1 now blocks it | Invert the assertion to expect `SecurityError`. |

### 8.6 Twig 3.27.1 - Bugfix Patch (Two Sandbox Regression Fixes)

Twig 3.27.1 (released 30 May 2026) is the current release - a **bugfix patch** over 3.27.0. It fixes two regressions that the 3.27.0 sandbox hardening introduced; it adds no features, no new CVEs, and no new deprecations. If you are on 3.27.0, upgrade straight to 3.27.1.

| PR | Regression introduced in 3.27.0 | Fix in 3.27.1 |
|---|---|---|
| [#4821](https://github.com/twigphp/Twig/pull/4821) | The sandbox materialized `Traversable` arguments into a plain `array` (part of the 3.27.0 `Traversable` coercion, section 8.1), so a function typed against a concrete iterable class broke - e.g. passing Symfony's `FormView` to `form_errors()` in a sandboxed template failed with a type error. | The sandbox now walks an `IteratorAggregate` in place and passes the ORIGINAL object through unchanged, instead of replacing it with an array. |
| [#4822](https://github.com/twigphp/Twig/pull/4822) | Array access with a `Stringable` key was inconsistent: the optimized inline path threw, while the regular path coerced the key to a string - e.g. `{{ menu[section] }}` where `section` is a `Stringable`. | The optimized path now coerces the key the same way as the regular path, still applying the sandbox `__toString` policy check. |

Dockraft stub impact: **none by default.** The stub is API-Platform-first (no Symfony Forms on the REST surface) and does not run a `SandboxExtension` over untrusted templates out of the box, so neither regression is reachable in the default stub. The fixes matter only if you (a) render a Symfony `FormView` inside a sandboxed template, or (b) index a collection with a `Stringable` key in a sandboxed template. Pin `^3.27.1` either way - it is a strict superset of 3.27.0's security fixes.

## 9. Version Constraints

| Package | Required |
|---|---|
| `twig/twig` | `^3.23` (for `=`, `?.`, `===`, destructuring), `^3.25` (for `needs_is_sandboxed`, deterministic embeds, overridable `EscaperRuntime`), `^3.26` (for sandbox hardening + `CoercesChildrenToStringInterface`), `^3.27` (additional sandbox fixes, `SecurityPolicy::setStrict()`, `SourcePolicyInterface` deprecation), `^3.27.1` (current - fixes two 3.27.0 sandbox regressions: typed-iterable / `FormView` args and `Stringable` array keys). Pin `^3.27.1` for the full security posture without the 3.27.0 regressions. |
| `twig/extra-bundle` | `^3.27` (REQUIRED when used - the `is_safe` annotation fixes ship from 3.26 in this package; bumping `twig/twig` alone leaves the XSS surface open) |
| `symfony/twig-bundle` | `^7.3` (for `#[AsTwigFilter]` / `#[AsTwigFunction]` / `#[AsTwigTest]`), `^8.1` required for the `twig.safe_class` resource tag (available in this stub) |
