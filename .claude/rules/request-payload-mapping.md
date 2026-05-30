# Improved Request Payload Mapping

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-18 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-improved-request-payload-mapping
* https://symfony.com/doc/8.1/controller.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Attribute/MapRequestPayload.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Attribute/MapQueryString.php

Symfony 8.1 adds four additive improvements to `#[MapRequestPayload]`, `#[MapQueryString]`, and `#[MapUploadedFile]`: uploaded-file mapping into DTOs, variadic controller arguments, the `mapWhenEmpty` option, and dynamic `validationGroups` via `Expression` / `Closure`. `#[MapQueryParameter]` is **unchanged**.

## 1. What Changed in Symfony 8.1

All four changes are **additive** - 8.0 controllers keep working.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| DTO contains scalar fields + `UploadedFile` properties (multipart) | Not supported - `$request->files` had to be merged manually OR controller split into two | `#[MapRequestPayload]` automatically merges request parameters and uploaded files (including nested arrays) before deserialization |
| Receive N denormalized items from a JSON array body | Typed array parameter with the `type` option | **Variadic parameter** - `MyDto ...$items` (idiomatic PHP) |
| Empty body / empty query string | Nullable param → `null`; Serializer never invoked | New `mapWhenEmpty: true` option - denormalizer runs with `[]`, custom denormalizers can populate the DTO from context |
| `validationGroups` | Static `string[]` only | Accepts `Expression`, `Closure`, OR `string[]` - computed from resolved controller arguments |

Attributes affected by each change:

| Change | `#[MapRequestPayload]` | `#[MapQueryString]` | `#[MapUploadedFile]` | `#[MapQueryParameter]` |
|---|---|---|---|---|
| Uploaded files in DTO | ✅ | - | - | - |
| Variadic params | ✅ | ✅ | ✅ | - |
| `mapWhenEmpty` | ✅ | ✅ | - | - |
| Dynamic `validationGroups` | ✅ | ✅ | - | - |

## 2. Uploaded Files Inside a `#[MapRequestPayload]` DTO

Before 8.1, a DTO that mixed scalar fields with `UploadedFile` properties could NOT be hydrated by `#[MapRequestPayload]` from a `multipart/form-data` request. Workarounds were either two parameters (one DTO + one `#[MapUploadedFile]`) or manual merging of `$request->files->all()` with `$request->request->all()`.

Since 8.1, the resolver merges both buckets transparently - nested file arrays included.

```php
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class ProductDto
{
    public ?string        $name  = null;
    public ?UploadedFile  $image = null;
}

final class ProductController
{
    public function upload(
        #[MapRequestPayload] ProductDto $data,
    ): Response {
        // $data->name  - from form field
        // $data->image - fully-resolved UploadedFile (or null if absent)
    }
}
```

Request shape: `Content-Type: multipart/form-data` with fields `name=...` and `image=@file.jpg`.

**Constraints:**
- The DTO property type must be `UploadedFile` (or `?UploadedFile`). The framework does NOT coerce a string filename into an `UploadedFile`.
- For multiple files under the same key, type the property as `array` (or `UploadedFile[]` via PHPDoc) and post the field as `images[]`.
- Validation of file size / mime type still goes through `#[Assert\File(...)]` on the property - declare it explicitly.

## 3. Variadic Controller Arguments

When the request payload (or query string, or uploaded-file collection) is a **list of items**, declare the controller parameter as variadic. The framework unpacks each element as a separate denormalized argument. This replaces the older `type: MyDto::class` option on a typed-array parameter - variadic is now the idiomatic form.

```php
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class Price
{
    public int $value = 0;
}

final class PriceController
{
    public function createPrices(
        #[MapRequestPayload] Price ...$prices,
    ): Response {
        foreach ($prices as $price) {
            // $price->value
        }
    }
}
```

Request body: `[{"value": 50}, {"value": 23}]` → `$prices` contains two `Price` instances.

Works the same way for:
- `#[MapQueryString] FilterDto ...$filters` - when the query is a JSON-encoded array (rare; usually `#[MapQueryParameter]` is more appropriate for scalar query args)
- `#[MapUploadedFile] UploadedFile ...$files` - for endpoints accepting multiple files under a repeating field name

**Note:** the variadic parameter MUST be the last parameter of the action (PHP language rule, not Symfony-specific).

## 4. `mapWhenEmpty` - Denormalize Empty Input

By default, on an empty request body (or absent query string), the resolver returns `null` for a nullable parameter WITHOUT invoking the Serializer. This is correct for "no input → no DTO" semantics but blocks a useful pattern: a custom denormalizer that builds the DTO from the **security context, session, or other ambient state** rather than from the request body.

Set `mapWhenEmpty: true` to force denormalization. The denormalizer receives an empty array (`[]`) and can populate the DTO from any source it sees fit.

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

final class SearchController
{
    public function search(
        #[MapQueryString(mapWhenEmpty: true)] SearchFilters $filters,
    ): Response {
        // Even with an empty query string, $filters is populated:
        // e.g. a custom denormalizer injected $filters->userId from
        // the security context, $filters->tenantId from a request header, etc.
    }
}
```

Available on `#[MapRequestPayload]` and `#[MapQueryString]`. Not applicable to `#[MapUploadedFile]` (no "empty file" denormalization) or `#[MapQueryParameter]` (scalar resolution path).

**When to use:**
- The DTO has fields that come from the user identity rather than the request (tenant ID, user ID, default locale).
- Optional filters with sensible server-side defaults: an empty `/search` should still execute "search for me with my preferences".

**When NOT to use:**
- Plain endpoints where empty input MUST mean `null` (e.g. partial-update where absent body = no-op). Leaving the default is correct.

## 5. Dynamic `validationGroups` - `Expression` / `Closure`

Pre-8.1, `validationGroups` accepted only a static `string[]`. Symfony 8.1 also accepts an `Expression` (evaluated against the resolved controller arguments) OR a `Closure` (invoked with the resolved arguments). The selected groups are then applied to the Validator after denormalization.

**Expression form:**

```php
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class UserController
{
    #[Route('/users/{user}', methods: ['PUT'])]
    public function update(
        User $user,
        #[MapRequestPayload(
            validationGroups: [new Expression('args["user"].getType()')],
        )] UpdateUserDto $dto,
    ): Response {
        // Validation groups selected from the already-resolved $user.
        // E.g. getType() === 'admin' applies the "admin" validation group.
    }
}
```

The expression context exposes `args` (the resolved controller arguments array, keyed by parameter name), plus `request` (the `Request`) and `this` (the controller instance). The dynamic value is resolved through `ControllerArgumentsEvent::evaluate()` - the same evaluation path as the 8.1 `#[Cache]` `if`/`etag` values (see `dynamic-controller-attributes.md` §6).

**Closure form (preferred for non-trivial logic):**

```php
use Closure;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/users/{user}', methods: ['PUT'])]
public function update(
    User $user,
    #[MapRequestPayload(
        validationGroups: static fn (array $args): array =>
            $args['user']->isAdmin() ? ['Default', 'admin'] : ['Default'],
    )] UpdateUserDto $dto,
): Response {
    // ...
}
```

The closure is invoked as `$closure($args, $request, $controller)` - it receives `(array $args, Request $request, ?object $controller)`, where `$args` is the same shape as the expression's `args` variable. Declare only the parameters you use (a `fn (array $args)` is fine - the trailing arguments are simply ignored). It must return `string[]` (or a `GroupSequence`).

**Why this matters:** previously, applying stricter validation rules for admins than regular users required either two separate routes/actions or a manual `Validator::validate($dto, null, $groups)` call inside the action body. The dynamic form keeps validation declarative AND data-dependent.

**Prefer Closure over Expression when:**
- Logic spans multiple lines or method calls.
- Group selection depends on something not easily expressed in Expression Language.
- You want PHPStan / Psalm to type-check the resolution.

Available on `#[MapRequestPayload]` and `#[MapQueryString]`.

## 6. Interaction with API Platform (Dockraft Stub Context)

The Dockraft Symfony stub ships **API Platform 4.3** for resource-shaped CRUD under `/v1/...`. API Platform has its own input-DTO / state-provider pipeline that does NOT use `#[MapRequestPayload]`. The payload-mapping attributes apply to **non-API-Platform** controllers:

| Endpoint type | Tool | Why |
|---|---|---|
| Resource CRUD under `/v1/...` | API Platform `#[ApiResource]` + state providers/processors | Auto OpenAPI, JSON-LD, IRI resolution |
| Ad-hoc HTTP endpoint (webhook, RPC, admin upload form) | `#[MapRequestPayload]` / `#[MapQueryString]` / `#[MapUploadedFile]` | Lighter, fully typed, no resource machinery |

Do NOT add `#[MapRequestPayload]` to an API Platform action - input deserialization is already handled by the framework's state pipeline. Double resolution either errors or produces a wrapped DTO that the processor doesn't understand.

## 7. Anti-patterns

- **Mixing variadic + the legacy `type:` option** - pick one. Variadic is the idiomatic 8.1+ form; `type:` remains for backwards compatibility.
- **`mapWhenEmpty: true` on plain endpoints** - if there's no ambient state to inject, the resolver runs the denormalizer for nothing. Use the default.
- **Static `validationGroups` when the rule actually depends on a runtime argument** - the new dynamic form is the right tool; don't fall back to two duplicate controllers.
- **`#[MapRequestPayload]` on an API Platform `#[ApiResource]` collection-create action** - collides with the state pipeline. Use an input DTO + state processor instead.
- **`UploadedFile` DTO property without `#[Assert\File(...)]`** - uploaded files are user-controlled. Always declare size + mime-type constraints.
- **Variadic parameter in non-last position** - PHP throws a parse error. The variadic parameter MUST be last.

## 8. Migration Notes (8.0 → 8.1)

No migration forced. Adopt incrementally:

1. **Multipart upload endpoints** previously split into two parameters can collapse into a single DTO with an `UploadedFile` property.
2. **Bulk-create endpoints** previously using `#[MapRequestPayload(type: Dto::class)] array $items` can switch to `#[MapRequestPayload] Dto ...$items`.
3. **Endpoints where empty input must still populate the DTO from context** - opt into `mapWhenEmpty: true` + write the custom denormalizer.
4. **Endpoints branching on user type / role** for validation - replace duplicate controllers with `validationGroups: new Expression(...)` or a closure.

## 9. Parameter Reference

### `#[MapRequestPayload]`

| Option | Type | Since | Purpose |
|---|---|---|---|
| `acceptFormat` | `string\|string[]` | 8.0 | Accepted request formats (default: framework defaults) |
| `serializationContext` | `array` | 8.0 | Passed verbatim to Serializer |
| `resolver` | `string` | 8.0 | Override the resolver service |
| `validationGroups` | `string\|array\|Expression\|GroupSequence\|Closure\|null` | 8.0 (8.1 adds `Expression`/`Closure`) | Validation groups applied after denormalization |
| `validationFailedStatusCode` | `int` | 8.0 | HTTP status on validation failure (default 422) |
| `type` | `string\|null` | 8.0 | Element type for typed-array params (superseded by variadic in 8.1) |
| `mapWhenEmpty` | `bool` | **8.1** | Run denormalizer on empty input |

### `#[MapQueryString]`

Same options as `#[MapRequestPayload]` minus `acceptFormat`. `mapWhenEmpty` and `validationGroups` widening added in 8.1.

### `#[MapUploadedFile]`

Variadic param support added in 8.1. Existing `name`, `constraints` options unchanged.

### `#[MapQueryParameter]`

**No changes in 8.1.** Continues to resolve single scalar query parameters as before.
