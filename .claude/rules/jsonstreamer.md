# JsonStreamer - 8.1 Improvements

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-28 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-improved-json-streaming-and-querying
* https://symfony.com/doc/8.1/json_streamer.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/JsonStreamer/Transformer/ValueObjectTransformerInterface.php

`symfony/json-streamer` is the JSON-only, high-throughput encoder/decoder (`StreamWriterInterface` / `StreamReaderInterface`, `#[JsonStreamable]`, `Type` from `symfony/type-info`). It is **opt-in** - `composer require symfony/json-streamer` - and not installed by the Dockraft stub by default. This rule documents only the **additive improvements in Symfony 8.1**; for base usage see the official docs. The companion JSON-querying rule is `jsonpath.md`.

## 1. What changed in 8.1

| Capability | Before 8.1 | Symfony 8.1 |
|---|---|---|
| Represent an object as a single scalar (not a nested JSON object) | Hand-written per case | `ValueObjectTransformerInterface` - a generic, auto-registered transformer |
| `DateInterval` / `DateTimeZone` | Treated as plain objects (nested) | Built-in value objects: ISO-8601 duration string / timezone name |
| Timezone on `DateTimeInterface` encode/decode | Not configurable | `date_time_timezone` option (string or `\DateTimeZone`) |
| Default options for every operation | Passed per call | `framework.json_streamer.default_options` config |

All additive - existing 8.0 JsonStreamer code keeps working.

## 2. Value-object transformers - `ValueObjectTransformerInterface`

Implement `Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface` to map an object to/from a single scalar. The transformer is auto-registered, and JsonStreamer then writes/reads the scalar directly instead of traversing the object's properties.

```php
use App\Money;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

final class MoneyValueObjectTransformer implements ValueObjectTransformerInterface
{
    public function transform(object $object, array $options = []): int|float|string|bool|null
    {
        return $object->amount.' '.$object->currency;        // Money -> "1299 EUR"
    }

    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object
    {
        [$amount, $currency] = explode(' ', $scalar);

        return new Money((int) $amount, $currency);
    }

    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();                               // the scalar wire type
    }

    public static function getValueObjectClassName(): string
    {
        return Money::class;                                 // the object this transformer applies to
    }
}
```

- The `$options` array is the per-operation options (including any custom keys from `default_options`, see section 5).
- `getStreamValueType()` returns a `BuiltinType` (`Type::string()`, `Type::int()`, ...) describing the scalar form.
- Use this instead of a hand-rolled normalizer when an object has a clean single-scalar representation (money, a coordinate pair, a hash, a SKU).

## 3. Built-in `DateInterval` and `DateTimeZone` value objects

Handled as value objects out of the box - no custom transformer needed:

- `DateInterval` serializes to an ISO-8601 duration string (e.g. `P2Y6M1DT12H30M5S`).
- `DateTimeZone` serializes to its name (e.g. `"Europe/Paris"` or `"+02:00"`).

Customize the duration format with the `date_interval_format` option:

```php
$json = $jsonStreamWriter->write($task, Type::object(Task::class), [
    'date_interval_format' => 'P%yY%mM%dDT%hH%iM%sS',
]);
```

## 4. Timezone conversion - `date_time_timezone`

Convert the timezone of `DateTimeInterface` values when encoding or decoding. Accepts a string or a `\DateTimeZone` instance:

```php
// Encode in Tokyo time
$json = $jsonStreamWriter->write($event, Type::object(Event::class), [
    'date_time_timezone' => 'Asia/Tokyo',
]);

// Decode, converting into Mexico City time
$event = $jsonStreamReader->read($json, Type::object(Event::class), [
    'date_time_timezone' => new \DateTimeZone('America/Mexico_City'),
]);
```

## 5. Default options - `framework.json_streamer.default_options`

Define options applied automatically to every read/write, instead of repeating them at each call site. Built-in options (e.g. `include_null_properties`) and custom options (forwarded to your transformers' `transform()` / `reverseTransform()` via their `$options` argument) are both supported:

```yaml
# config/packages/framework.yaml
framework:
    json_streamer:
        default_options:
            include_null_properties: true
            my_custom_option: 'my_custom_value'
```

## 6. Notes

- Still JSON-only and shape-stable - the component is for large, typed payloads streamed via `StreamedResponse`. For format negotiation or polymorphism, use the Serializer; for API Platform resources, use API Platform's own pipeline.
- A value-object transformer is symmetric: `transform` and `reverseTransform` must round-trip, or decode will not reconstruct the object.
- `date_interval_format` only affects `DateInterval`; `date_time_timezone` only affects `DateTimeInterface`. They are independent options.

## 7. Version constraints

| Package | Required |
|---|---|
| `symfony/json-streamer` | `^8.1` (for `ValueObjectTransformerInterface`, built-in `DateInterval`/`DateTimeZone`, `date_time_timezone`, `framework.json_streamer.default_options`) |
| `symfony/type-info` | `^8.1` (transitive - provides `Type` / `BuiltinType`) |
