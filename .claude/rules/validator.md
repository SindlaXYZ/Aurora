# Symfony Validator - 7.3 / 7.4 / 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-19 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-7-3-validator-improvements
* https://symfony.com/blog/new-in-symfony-7-3-slug-and-twig-constraints
* https://symfony.com/blog/new-in-symfony-7-4-misc-features-part-1
* https://symfony.com/blog/new-in-symfony-7-4-video-constraint
* https://symfony.com/blog/new-in-symfony-7-4-extending-validation-and-serialization-with-php-attributes
* https://symfony.com/blog/new-in-symfony-8-1-validator-improvements
* https://symfony.com/doc/8.1/validation.html
* https://symfony.com/doc/8.1/reference/configuration/framework.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Validator/ConstraintViolationListInterface.php

Covers validator additions that change how constraints are written in NEW code on Symfony 8.1. Existing constraints in the stub remain valid - this rule applies to anything written from now on.

## 1. Always Use Named Arguments

The array-based config form is deprecated since 7.3. Removed in a future major.

```php
// Deprecated since 7.3
new Assert\Choice(['choices' => ['a', 'b'], 'message' => 'Bad value.'])

// Required form (named arguments)
new Assert\Choice(choices: ['a', 'b'], message: 'Bad value.')
```

Rule: **every constraint in this stub MUST use named arguments**, including `#[Assert\*]` attributes. Reviewing PRs: array-form constraints in NEW code are a hard reject.

## 2. `When` Constraint - `otherwise:` Branch

Apply different constraints depending on an expression. Replaces the old pattern of two separate `When` constraints with mutually exclusive expressions.

```php
use Symfony\Component\Validator\Constraints as Assert;

class Discount
{
    public string $type = 'percent';   // 'percent' | 'fixed'

    #[Assert\When(
        expression: 'this.type == "percent"',
        constraints: [new Assert\LessThanOrEqual(100), new Assert\PositiveOrZero()],
        otherwise:   [new Assert\LessThan(9999), new Assert\PositiveOrZero()],
    )]
    public int $value = 0;
}
```

## 3. `When` Constraint - Closures

Closures inside attributes work in Symfony 7.3+. The `static` keyword satisfies the attribute constant-expression rule on PHP versions where it would otherwise complain; Symfony evaluates the closure lazily at validation time:

```php
#[Assert\When(
    expression: static function (BlogPost $post): bool {
        return $post->published && !$post->draft;
    },
    constraints: [new Assert\NotNull(), new Assert\NotBlank()],
)]
public ?string $headline = null;
```

Prefer string expressions when the condition is trivial (`'this.type == "percent"'`) - they're easier to scan. Reach for closures only when the logic requires PHP (multiple checks, method calls, type casts).

## 4. `Url` - Allow Any Protocol

For "any RFC-3986 scheme is OK" (custom protocols, `git+ssh://`, `vscode://`, etc.) without listing them one by one:

```php
#[Assert\Url(protocols: ['*'])]
public string $bioUrl = '';
```

Rule: **`['*']` is a wildcard, not a default.** Without `protocols:`, Symfony enforces `['http', 'https']` - which is the safer default for user-supplied URLs. Use `['*']` only when the field is genuinely free-form (developer integrations, package URIs, internal-tool links).

## 5. `Image` - SVG Aspect Ratio Support

Aspect-ratio options (`allowLandscape`, `allowPortrait`, `allowSquare`, `minRatio`, `maxRatio`) now apply to SVG uploads (Symfony 7.3+). No code change needed - just stop manually exempting SVGs from these checks.

```php
#[Assert\Image(
    allowSquare: false,
    minRatio: 1.4,
    maxRatio: 2.4,
    mimeTypes: ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'],
)]
public ?UploadedFile $hero = null;
```

## 6. `File` - Filename Counting Unit

Long filenames may be reported as too long because of how Symfony counts characters. Default is `FILENAME_COUNT_BYTES` (legacy). For unicode-heavy filenames, pick a saner counting unit:

```php
use Symfony\Component\Validator\Constraints\File;

#[Assert\File(
    maxSize: '10M',
    filenameMaxLength: 200,
    filenameCountUnit: File::FILENAME_COUNT_GRAPHEMES,
    filenameCharset: 'UTF-8',
)]
public ?UploadedFile $attachment = null;
```

Counting units:

| Unit | Notes |
|---|---|
| `FILENAME_COUNT_BYTES` (default) | Raw byte count. Legacy behavior. |
| `FILENAME_COUNT_CODEPOINTS` | Counts Unicode codepoints via `mb_strlen`. OK for most apps. |
| `FILENAME_COUNT_GRAPHEMES` | Counts user-perceived characters (emoji-aware). Best UX. Requires `ext-intl`. |

## 7. `Unique` - Report All Duplicates

The default reports only the first duplicate, then stops. Set `stopOnFirstError: false` to surface every duplicate at once - better UX for "fix all these errors" interfaces.

```php
#[Assert\Unique(stopOnFirstError: false)]
public array $tags = [];
```

## 8. Disable Validation Translations

For pure JSON APIs that return raw constraint messages (no user-facing UI), translation overhead is unnecessary. Symfony 7.3+ has a one-line opt-out:

```yaml
# config/packages/framework.yaml
framework:
    validation:
        disable_translation: true
```

Rule: enable this in **API-only** projects (no Twig forms, no admin UI). It removes the translator dependency from the validator service graph; constraint messages come through verbatim (or via `messageId:` lookup against a project-side dictionary).

## 9. `Twig` Constraint (Symfony 7.3+)

Validates that user-supplied content is a valid Twig template - useful when the project lets editors author email templates or report layouts:

```php
use Symfony\Bridge\Twig\Validator\Constraints\Twig;

class EmailTemplate
{
    #[Twig]
    public string $body = '';

    // Stricter: fail on deprecation warnings, not just hard parse errors.
    #[Twig(skipDeprecations: false)]
    public string $subject = '';
}
```

Rule: enable this on any column where editors compose Twig. Without it, the syntax error surfaces at SEND time, after persisting bad content.

## 10. Slug Validation - Use `Regex` with `Requirement::ASCII_SLUG`

A dedicated `Slug` constraint shipped in 7.3 beta then was REMOVED before stable because there is no canonical slug spec. Use `Regex` instead - pinning to the same pattern the routing component uses keeps everything aligned:

```php
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

class Article
{
    #[Assert\Regex('/^' . Requirement::ASCII_SLUG . '$/')]
    public string $slug = '';
}
```

For stricter rules: `/^[a-z0-9]+(?:-[a-z0-9]+)*$/` (lowercase only) or `/^[a-z](?:[a-z0-9]+)?(?:-[a-z0-9]+)*$/` (no leading number).

## 11. `Video` Constraint (Symfony 7.4+)

Counterpart to `Image`, for video uploads. Requires `ffmpeg` (specifically `ffprobe`) installed inside the container - the constraint shells out to extract metadata.

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;

class Submission
{
    #[Assert\Video(
        maxWidth: 1920,
        maxHeight: 1080,
        maxSize: '100M',
        mimeTypes: ['video/mp4', 'video/webm'],
        allowPortrait: false,
        allowedCodecs: ['h264', 'hevc'],
        allowedContainers: ['mp4', 'webm'],
    )]
    public ?File $video = null;
}
```

Options:

- Dimensions: `minWidth`, `maxWidth`, `minHeight`, `maxHeight`, `minPixels`, `maxPixels`.
- Aspect ratio: `minRatio`, `maxRatio`, `allowLandscape`, `allowPortrait`, `allowSquare`.
- Codec / container: `allowedCodecs` (default: `h264`, `hevc`, `vp9`, `av1`), `allowedContainers` (default: `mp4`, `webm`, `mkv`), `mimeTypes`.

Rules:

1. **Install ffmpeg in `.docker/Dockerfile` BEFORE adopting.** A `Video` constraint on a host without `ffprobe` raises a runtime exception.
2. **Always pin `allowedCodecs` / `allowedContainers`.** Defaults are permissive; restrict to what your downstream player (or transcoder) supports.
3. **Combine with `maxSize`.** Codec validation does not prevent an attacker from uploading a giant valid-MP4 padding file.

## 12. `#[ExtendsValidationFor]` - External Class Validation (Symfony 7.4+)

When constraints must be applied to a class you do NOT own (vendor entity, generated stub, third-party DTO), declare an extension class:

```php
use Acme\Vendor\Bundle\UserRegistration;
use Symfony\Component\Validator\Attribute\ExtendsValidationFor;
use Symfony\Component\Validator\Constraints as Assert;

#[ExtendsValidationFor(UserRegistration::class)]
abstract class UserRegistrationValidation
{
    #[Assert\NotBlank(groups: ['my_app'])]
    #[Assert\Length(min: 3, groups: ['my_app'])]
    public string $name = '';

    #[Assert\Email(groups: ['my_app'])]
    public string $email = '';

    #[Assert\Range(min: 18, groups: ['my_app'])]
    public int $age = 0;
}
```

Rules:

1. **Property / getter names MUST match the target class exactly.** Symfony verifies at container compile time.
2. **Declare the class `abstract`** - it's a metadata vessel, not an instantiable class.
3. **Apply constraints to GROUPS named after the project** (`my_app`, `admin_form`, ...) - never the default group. The target class may have its own default-group constraints from the vendor, and merging silently is risky.
4. **Live under `src/Validator/Extension/`** by convention - keeps the override visible without polluting the entity layer.
5. **NOT a replacement for forking the class.** When constraints diverge fundamentally, extending the validation does not save you from also extending the class. Use this for additive constraints only.

## 13. `Xml` Constraint - Validate Well-Formed XML (Symfony 8.1+)

Built-in constraint for validating well-formed XML and, optionally, against an XSD schema:

```php
use Symfony\Component\Validator\Constraints as Assert;

class Report
{
    // Well-formed XML only.
    #[Assert\Xml]
    public string $rawContent = '';

    // Well-formed AND matches the XSD schema.
    #[Assert\Xml(schemaPath: 'config/schemas/report.xsd')]
    public string $validatedContent = '';
}
```

Rules:

1. **`schemaPath:` is resolved at constraint-construction time.** A missing schema file fails fast with `InvalidArgumentException` at boot, NOT at validation time - typos and stale deploys are caught early.
2. **Per-error reporting.** When the XSD has multiple violations, each schema error is emitted as a separate `ConstraintViolation` with the offending line number. The UI surfaces all problems at once instead of stopping at the first.
3. **Pair with `NotBlank` for required-XML fields.** The `Xml` constraint accepts an empty string as valid (vacuously well-formed). Add `#[Assert\NotBlank]` when emptiness is itself a failure.
4. **Store schemas under `config/schemas/`** by stub convention. The path is project-relative; do NOT use absolute paths that break across DEV / PROD containers.

## 14. Clock-Aware Comparison and Range Validators (Symfony 8.1+)

`GreaterThan`, `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual`, and `Range` now resolve relative date strings (`today`, `-18 years`, `+30 minutes`) against the configured `ClockInterface` instead of the wall clock. Same pattern as `UriSigner` (see `uri-signer.md`): production code stays unchanged; tests inject `MockClock`.

```php
use Symfony\Component\Validator\Constraints as Assert;

class Article
{
    // Resolved against the Clock service - testable with MockClock.
    #[Assert\GreaterThan('-30 days')]
    public \DateTimeImmutable $publishedAt;

    #[Assert\Range(min: '-1 year', max: 'today')]
    public \DateTimeImmutable $eventDate;
}
```

Testing pattern (FrameworkBundle 8.1+ auto-wires the Clock to these validators - no manual wiring needed when running through the full validator service):

```php
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;

public function testRespectsFrozenNow(): void
{
    $clock     = new MockClock('2026-05-20 00:00:00 UTC');
    $validator = new GreaterThanValidator(null, $clock);

    // "-10 days" resolves to 2026-05-10 against the frozen clock.
    $validator->validate(
        new \DateTimeImmutable('2026-05-12 00:00:00 UTC'),
        new GreaterThan('-10 days'),
    );
}
```

Rules:

1. **Use relative date strings deliberately.** `-30 days` is readable; magic numbers (`new \DateTime('@1234567890')`) are not. Stick with the relative form in constraint definitions.
2. **`MockClock` in every date-related test.** Tests that hit the validators with relative-string constraints are flaky without a frozen clock - they pass at 23:59:59 and fail at 00:00:01 on the same fixture.
3. **Pair with `DatePoint` properties** (see `datepoint.md`). `DatePoint` types and Clock-aware validators are designed to be used together: deterministic in tests, frictionless in production.

## 15. Strict Property Metadata Existence Check (Symfony 8.1+)

By default, validating a property that does NOT exist on the target class silently returns zero violations - `validateProperty('typoName')` looks like a "no constraints failed" result, masking the typo. Symfony 8.1 adds an opt-in strict mode that throws `ValidatorException` instead.

**In a FrameworkBundle app (the stub) - enable via config.** This is the form to use here; it flips the framework's `validator.builder` service into strict mode without any custom wiring:

```yaml
# config/packages/validator.yaml
framework:
    validation:
        property_metadata_existence_check: true   # default: false
```

**Standalone (no FrameworkBundle) - enable on the builder.** Code that constructs its own `ValidatorBuilder` (custom compilers, batch tools, schema generators) calls the method directly before `getValidator()`:

```php
use Symfony\Component\Validator\Validation;

$validator = Validation::createValidatorBuilder()
    ->enablePropertyMetadataExistenceCheck()
    ->getValidator();

// Throws ValidatorException: the property "nmae" does not exist in class "Author"
$validator->validateProperty($author, 'nmae');
```

The config key and the builder method are two entry points to the same behavior - the framework config just calls `enablePropertyMetadataExistenceCheck()` on the `validator.builder` definition for you.

Rules:

1. **Off by default - opt in deliberately.** Both the config key (`false`) and the builder are off until you turn them on. In the stub, set `property_metadata_existence_check: true` in `validator.yaml`; do NOT hand-build a `ValidatorBuilder` just to call the method.
2. **Catch typos at the boundary.** The strict check is most valuable when validating fields supplied by dynamic input (form field names, JSON keys mapped to property paths). Without it, an attacker-supplied unknown field returns "no violations" - looks valid even though no constraints applied to it.
3. **Cost is negligible.** The check runs once per property name; no perceivable overhead in normal validator usage.
4. **Beware mixed-shape payloads.** If some valid requests legitimately omit properties that exist only on certain subtypes, enabling the strict check globally can convert a benign "extra field" into a hard `ValidatorException`. Scope validation to the right class / groups, or leave the check off for that path.

## 16. Reentrant Constraint Validators - `validateInContext()` (Symfony 8.1+)

`ConstraintValidatorInterface` gained a `validateInContext()` method that receives the execution context as an EXPLICIT argument. The legacy stateful `initialize(ExecutionContextInterface $context)` + `validate($value, Constraint $constraint)` pair is deprecated.

Motivation: the old API stored the context on the validator instance, so recursive validation (e.g. `CollectionValidator` invoking the per-element validator) corrupted the outer context. The new method passes the context as an argument - fully reentrant.

```php
// Old (deprecated):
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MyValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

// New (Symfony 8.1+):
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MyValidator extends ConstraintValidator
{
    public function validateInContext($value, Constraint $constraint, ExecutionContextInterface $context): void
    {
        if (!$value) {
            $context->buildViolation($constraint->message)->addViolation();
        }
    }
}
```

Rules:

1. **Custom validators EXTENDING `ConstraintValidator` work unchanged.** The base class plumbs the context for you - keeping the legacy `validate(...)` method is fine in 8.1. Migrate to `validateInContext()` only when the validator legitimately recurses into the validator service (most projects never do).
2. **Custom validators IMPLEMENTING `ConstraintValidatorInterface` directly MUST migrate.** The interface's old method is deprecated; new code should override `validateInContext()` instead.
3. **`ConstraintValidatorTestCase` got a helper.** Tests should call `$this->validate($value, $constraint)` (a NEW method on the test case) instead of `$this->validator->validate(...)` - the helper works against both legacy and new validators, so the test stays compatible across the migration.

```php
final class MyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MyValidator
    {
        return new MyValidator();
    }

    public function testRejectsFalsyValues(): void
    {
        // NEW helper - works with both legacy validate() and new validateInContext()
        $this->validate(false, new MyConstraint());
        $this->buildViolation('...')->assertRaised();
    }
}
```

## 17. Filter Violations by Error Code - `findByCodes()` (Symfony 8.1+)

`ConstraintViolationListInterface` gained `findByCodes()`: it returns a NEW list containing only the violations whose error code matches. Each constraint exposes its codes as class constants (e.g. `UniqueEntity::NOT_UNIQUE_ERROR`, `NotBlank::IS_BLANK_ERROR`), so this is the stable way to branch on a specific failure instead of string-matching messages.

Declared on the interface as `@method static findByCodes(string|string[] $codes)` - it accepts one code or an array of codes and returns a filtered `ConstraintViolationListInterface`.

```php
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

$violations = $validator->validate($dto);

// React to ONE specific failure without scanning every violation by hand.
if (0 !== \count($violations->findByCodes(UniqueEntity::NOT_UNIQUE_ERROR))) {
    // e.g. return 409 Conflict instead of a generic 422
}
```

Rules:

1. **Branch on codes, never on messages.** Messages are translatable and can change; the error-code constants are the contract. `findByCodes()` makes the intent explicit.
2. **Pass an array to catch a family of codes at once** - `findByCodes([Foo::A_ERROR, Foo::B_ERROR])`. The return value is always a `ConstraintViolationListInterface`, so `count()` / iteration work as on the full list.
3. **Useful at the API boundary.** In the stub's JSON error handling, map a specific code (uniqueness, not-blank, length) to a tailored HTTP status or payload while leaving the rest of the violations in the default 422 response.

## 18. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Deprecated: Passing an array of options to "Symfony\Component\Validator\Constraints\..."` | Array-based constraint config | Convert to named arguments. |
| `Cannot use closures inside attribute arguments` | Missing `static` keyword on the closure | Use `static function (...)` form; Symfony evaluates lazily at validation time. |
| `Image constraint cannot be applied to non-image file` on an SVG | Validator < 7.3 OR mimeTypes does not include `image/svg+xml` | Upgrade and include the MIME type explicitly. |
| `Filename "..." is too long (max 100 characters)` for a unicode filename | Default `FILENAME_COUNT_BYTES` counts bytes | Switch to `FILENAME_COUNT_GRAPHEMES`. |
| `InvalidArgumentException: Schema file "..." does not exist` at boot | `Xml(schemaPath: ...)` points at a missing XSD | The path is project-relative and resolved at construction time. Verify the schema file is shipped in the container and the path matches. |
| Tests with `Assert\GreaterThan('-30 days')` flaky around midnight | Validator using wall clock instead of `MockClock` | Inject `MockClock` in tests; in Symfony 8.1+ FrameworkBundle wires the override automatically when the `clock` service is replaced in `services_test.yaml`. |
| `ValidatorException: the property "..." does not exist in class "..."` after upgrade | `framework.validation.property_metadata_existence_check: true` (or `enablePropertyMetadataExistenceCheck()`) is enabled and the calling code passes a typo or stale property name | The check is doing its job - fix the typo. If the use case genuinely accepts dynamic property names, do NOT enable the strict check for that path. |
| Deprecation: `Implementing "ConstraintValidatorInterface::validate()" is deprecated` | Custom validator implements the interface directly and overrides the old method | Switch to `validateInContext()`. Validators that EXTEND `ConstraintValidator` are not affected. |

## 19. Version Constraints

| Package | Required |
|---|---|
| `symfony/validator` | `^7.3` (for `When.otherwise`, `Unique.stopOnFirstError`, `Image` SVG, `File.filenameCountUnit`, `Url(protocols: ['*'])`, `Twig` constraint, deprecation of array config), `^7.4` (for `Video` constraint, `#[ExtendsValidationFor]`), `^8.1` (for `Xml` constraint, Clock-aware Comparison / Range validators, strict property-metadata existence check via `framework.validation.property_metadata_existence_check` / `enablePropertyMetadataExistenceCheck()`, `validateInContext()`, `ConstraintViolationListInterface::findByCodes()`) |
| `symfony/twig-bridge` | `^7.3` (provides the `Twig` validator constraint) |
| `symfony/clock` | `^8.1` (transitive - auto-wired into Comparison / Range validators by FrameworkBundle 8.1+) |
| `ffmpeg` (system) | required by the `Video` constraint - install via Dockerfile |
