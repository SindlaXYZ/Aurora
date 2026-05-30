# JsonPath - 8.1 Improvements

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-28 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-improved-json-streaming-and-querying
* https://symfony.com/doc/8.1/json_path.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/JsonPath/FunctionReturnType.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/JsonPath/Attribute/AsJsonPathFunction.php

`symfony/json-path` queries JSON documents with RFC 9535 JSONPath syntax (the JSON equivalent of DomCrawler). It is **opt-in** - `composer require symfony/json-path` - and not installed by the Dockraft stub by default. This rule documents only the **additive improvement in Symfony 8.1**; for base querying (`find()`, filter expressions, built-in functions) see the official docs. The companion JSON-streaming rule is `jsonstreamer.md`.

## 1. What changed in 8.1

Symfony 8.1 lets you register **custom functions** for use inside JsonPath filter expressions, via the new `#[AsJsonPathFunction]` attribute. Previously only the RFC 9535 built-ins (`length`, `count`, `match`, `search`, `value`) were available. Additive - existing queries are unaffected.

## 2. Custom functions - `#[AsJsonPathFunction]`

Apply `Symfony\Component\JsonPath\Attribute\AsJsonPathFunction` to an invokable class. Symfony auto-registers it, and the function name becomes usable inside filter expressions. The number of arguments is inferred from the `__invoke()` signature.

```php
use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;

#[AsJsonPathFunction('upper')]
final class UppercaseFunction
{
    public function __invoke(mixed $value): ?string
    {
        return \is_string($value) ? strtoupper($value) : null;
    }
}
```

Use it in a query like any built-in function:

```php
$crawler = $crawlerFactory->crawl($json);

// Keep items whose (uppercased) title equals "HELLO"
$result = $crawler->find('$.items[?upper(@.title) == "HELLO"]');
```

## 3. Return type - `FunctionReturnType`

The attribute's `returnType` argument declares how the function may be used in a filter. It takes a `Symfony\Component\JsonPath\FunctionReturnType` enum case (a string-backed enum shipped with the JsonPath component, modeling the RFC 9535 function type system):

| Case | Meaning | Where it is valid |
|---|---|---|
| `FunctionReturnType::Value` (default) | The function yields a single JSON value | In comparisons, e.g. `?upper(@.title) == "HELLO"` |
| `FunctionReturnType::Logical` | The function yields a boolean test | As a standalone filter test, e.g. `?isActive(@)` - and NOT comparable (`?exists(@.a) == true` is a query error) |
| `FunctionReturnType::Nodes` | The function yields a nodelist (array of matched nodes) | Not directly comparable; feeds the surrounding query rather than a comparison |

```php
use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;

#[AsJsonPathFunction('isActive', returnType: FunctionReturnType::Logical)]
final class IsActiveFunction
{
    public function __invoke(mixed $item): bool
    {
        return \is_array($item) && ($item['active'] ?? false) === true;
    }
}
```

`FunctionReturnType` is `Symfony\Component\JsonPath\FunctionReturnType`. The attribute signature is `AsJsonPathFunction(string $name, FunctionReturnType $returnType = FunctionReturnType::Value)`, so `returnType` is optional and defaults to `Value` - declare it only for `Logical` (or `Nodes`) functions.

## 4. Notes

- Treat external JSON as untrusted and do not interpolate user input into the path string - build queries with the fluent `JsonPath` builder, exactly as for built-in queries (RFC 9535 / docs).
- A `Value` function used as a standalone test (or a `Logical` function used in a comparison) is a query error - match `returnType` to how the function is actually used.
- Argument count must match the `__invoke()` parameter list; the resolver infers it from the signature.

## 5. Version constraints

| Package | Required |
|---|---|
| `symfony/json-path` | `^8.1` (for `#[AsJsonPathFunction]` and `FunctionReturnType`); `^7.3` for the base component |
