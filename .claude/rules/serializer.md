# Symfony Serializer - 7.3 / 7.4 / 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-7-3-serializer-improvements
* https://symfony.com/blog/new-in-symfony-7-4-misc-features-part-2
* https://symfony.com/blog/new-in-symfony-7-4-extending-validation-and-serialization-with-php-attributes
* https://symfony.com/blog/new-in-symfony-8-1-serializer-improvements
* https://symfony.com/doc/8.1/serializer.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

Covers Serializer additions through Symfony 8.1. The 7.3 / 7.4 features (sections 1-6) remain valid on 8.1; Symfony 8.1 adds three further items (section 7): `COLLECT_EXTRA_ATTRIBUTES_ERRORS`, `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION`, and a deprecation when a date cannot be parsed with the default format.

## 1. `DiscriminatorMap` - `defaultType:` Fallback

When the incoming payload is missing the discriminator field, deserialization used to throw. Symfony 7.3+ lets the developer pick a default target class instead:

```php
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        'article' => Article::class,
        'video'   => Video::class,
        'audio'   => Podcast::class,
    ],
    defaultType: 'article',
)]
abstract class Content {}
```

With this setup, deserializing `{"title": "x"}` (no `type` field) produces an `Article`.

Rules:

1. **Set `defaultType:` only when the project genuinely accepts payloads without the discriminator.** When the field is required, leave it unset - silent fallback to a default class can hide producer-side bugs.
2. **`defaultType:` must be a value that EXISTS in `mapping:`.** No magic class FQCN, no `null`. The serializer routes the default value through the same lookup as any other discriminator.
3. **Document the default** at the class level. A future reader needs to know which subtype is chosen for ambiguous payloads.

## 2. `ignore_empty_attributes` (XML Encoder)

Strips XML attributes with empty values from the OUTPUT. Useful when integrating with brittle XML consumers that reject `<foo bar=""/>` but accept `<foo/>`.

```php
$xml = $serializer->serialize($data, 'xml', ['ignore_empty_attributes' => true]);
```

Rule: prefer fixing the XSD on the consumer side; reach for this option only when the consumer is genuinely out of reach.

## 3. CDATA Wrapping by Name Pattern (7.4)

When specific fields must be wrapped in `<![CDATA[...]]>` regardless of their actual content (e.g. legacy XML schemas that require it on text columns):

```php
use Symfony\Component\Serializer\Encoder\XmlEncoder;

$xml = $serializer->serialize($data, 'xml', [
    XmlEncoder::CDATA_WRAPPING            => true,
    XmlEncoder::CDATA_WRAPPING_NAME_PATTERN => '/(firstname|lastname|body)/',
]);
```

Rule: pin the pattern to **known field names**, not content. CDATA-wrapping based on content already exists via `CDATA_WRAPPING` alone; the 7.4 addition is the name-based selection.

## 4. Number Normalizer

`BcMath\Number` and `GMP` are normalized to / from their string representations automatically when `symfony/serializer` 7.3+ is present. Use them in entities when arbitrary-precision arithmetic matters (financial calculations, scientific computation):

```php
use BcMath\Number;

class Invoice
{
    public Number $total;
}
```

`$serializer->serialize($invoice, 'json')` produces `{"total": "12345.67"}` - string output (lossless), not float.

## 5. `debug:serializer` - Discriminator Map Output

The console command prints the discriminator mapping when inspecting a class:

```
bin/console debug:serializer 'App\Entity\Content'
```

Useful when chasing "wrong subtype deserialized" bugs.

## 6. `#[ExtendsSerializationFor]` - External Class Serialization (7.4+)

Counterpart of `#[ExtendsValidationFor]` for serializer metadata. Apply `Groups`, `SerializedName`, `MaxDepth`, etc. to a class you do NOT own:

```php
use Acme\Vendor\Bundle\UserRegistration;
use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ExtendsSerializationFor(UserRegistration::class)]
abstract class UserRegistrationSerialization
{
    #[Groups(['my_app'])]
    #[SerializedName('fullName')]
    public string $name = '';

    #[Groups(['my_app'])]
    public string $email = '';

    #[Groups(['my_app'])]
    #[MaxDepth(2)]
    public Category $category;
}
```

Rules:

1. **`abstract` keyword required.** The extension class is a metadata vessel, not an instantiable type.
2. **Property / accessor names match the target.** Compile-time verification catches typos.
3. **Live under `src/Serializer/Extension/`** for parity with `src/Validator/Extension/`.
4. **Use project-scoped Groups (`my_app`, `admin`, ...)** not the default group - merging with vendor metadata in the default group is fragile.
5. **Do NOT change the target's identity.** `#[ExtendsSerializationFor]` adds metadata; it cannot change the FQCN seen by API Platform / Hydra. For renaming the resource entirely, fork the class.

## 7. What Changed in Symfony 8.1

Three additive items. Existing 8.0 serialization code keeps working; the date-parse change (7.3) is a deprecation, not a break.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Reporting unexpected (extra) attributes during deserialization | Single error; no full path to the offending node | `COLLECT_EXTRA_ATTRIBUTES_ERRORS` collects every extra-attribute error with the full deserialization path (7.1) |
| String values from string-only formats (XML / CSV / query strings) | Cast to a scalar target type failed with `UnexpectedValueException` | `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION` casts string -> `int` / `float` / `bool` (7.2) |
| A date value that does not match the default format | Silently mishandled | Triggers a deprecation (7.3) |

### 7.1 `COLLECT_EXTRA_ATTRIBUTES_ERRORS` - Full Extra-Attribute Reporting

When deserializing with `allow_extra_attributes: false` (extra fields are not allowed), the `COLLECT_EXTRA_ATTRIBUTES_ERRORS` context option collects EVERY extra-attribute violation - each carrying the full deserialization path to the offending node - instead of failing on the first one. This makes "your payload has unknown fields" responses actionable: the client sees all offending keys at once.

```php
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

$dto = $serializer->deserialize($json, OrderInput::class, 'json', [
    AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES   => false,
    AbstractObjectNormalizer::COLLECT_EXTRA_ATTRIBUTES_ERRORS => true,
]);
```

Rule: pair it with `allow_extra_attributes: false`. With extra attributes allowed there is nothing to collect. Use it at the API boundary so a malformed request returns a complete list of unknown fields rather than one-at-a-time rejections.

### 7.2 `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION` - Scalar Type Casting on Deserialize

Some formats encode every value as a string (XML, CSV, HTTP query strings). When deserializing payloads from these formats, this context option makes the normalizer CAST string values to the scalar type expected by the target property (`int`, `float`, or `bool`) instead of failing with an `UnexpectedValueException`.

```php
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

// "42" / "true" in the XML get cast to int / bool on the target DTO.
$dto = $serializer->deserialize($xml, ProductInput::class, 'xml', [
    AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => true,
]);
```

Rules:

1. **Reach for it on string-only formats.** JSON already carries native scalar types, so JSON deserialization rarely needs it; XML / CSV / query-string payloads are the target.
2. **Only `int` / `float` / `bool` are coerced.** It does not invent objects or arrays - it casts string scalars to the declared scalar property type.
3. **Keep validation downstream.** Type conversion is not validation: `"abc"` cast to `int` still needs `#[Assert\*]` on the property to reject garbage. Conversion only removes the "string where a scalar was expected" failure.

### 7.3 Deprecation - Date Not Parseable With the Default Format

Symfony 8.1 triggers a deprecation when a date value cannot be parsed with the default format during (de)serialization. The intent is to surface ambiguous date handling early rather than letting a misparsed date slip through.

Rule: set an explicit format when a property's date is not in the default shape, via `DateTimeNormalizer::FORMAT_KEY` in the serializer context (or `context: [...]` on the `#[Serialize]` attribute - see `.claude/rules/serialize-attribute.md`):

```php
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

$serializer->deserialize($json, Event::class, 'json', [
    DateTimeNormalizer::FORMAT_KEY => 'd.m.Y H:i:s',
]);
```

Do NOT silence the deprecation - pin the format so the parse is unambiguous.

## 8. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `The type "..." has no mapped class for the discriminator` | `defaultType:` points at a value not present in `mapping:` | Make sure the default value is one of the mapping keys. |
| Float precision loss on `Number` columns | `symfony/serializer` < 7.3 (no Number normalizer) | Upgrade `symfony/serializer` to `^8.1`. |
| Empty XML attributes leak through | Wrong context key | Use `'ignore_empty_attributes' => true`, not `ignore_empty_attribute`. |
| `UnexpectedValueException: The type of the "..." attribute must be "int", "string" given` on XML / CSV input | String-only format deserialized without type conversion | Enable `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION` (7.2). |
| Only the first unknown field reported on a malformed payload | `COLLECT_EXTRA_ATTRIBUTES_ERRORS` not set | Enable it alongside `allow_extra_attributes: false` (7.1). |
| Deprecation: date could not be parsed with the default format | A date value not in the default shape (8.1, 7.3) | Set `DateTimeNormalizer::FORMAT_KEY` explicitly for that property. |

## 9. Version Constraints

| Package | Required |
|---|---|
| `symfony/serializer` | `^7.3` (for `defaultType:`, `ignore_empty_attributes`, Number normalizer), `^7.4` (for `CDATA_WRAPPING_NAME_PATTERN`, `#[ExtendsSerializationFor]`), `^8.1` (for `COLLECT_EXTRA_ATTRIBUTES_ERRORS`, `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION`, and the unparseable-date deprecation) |
