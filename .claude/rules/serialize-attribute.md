# `#[Serialize]` Controller Return-Value Serializer

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-12 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-serialize-attribute
* https://symfony.com/doc/8.1/controller.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/Attribute/Serialize.php

The `Symfony\Component\HttpKernel\Attribute\Serialize` attribute lets a controller return a plain object (or array) instead of a `Response`. The framework serializes the return value using the request format (JSON by default) and wraps it in a `Response` with proper Content-Type, status code, and headers.

## 1. What Changed in Symfony 8.1

`#[Serialize]` is a **new** attribute - there is no pre-8.1 equivalent. The old pattern requires manually injecting `SerializerInterface`, calling `serialize()`, and constructing a `JsonResponse` (or equivalent).

| Concern | Pre-8.1 (manual) | Symfony 8.1 (`#[Serialize]`) |
|---|---|---|
| Inject `SerializerInterface` | Required | Not needed - framework wires it |
| Call `$serializer->serialize($data, 'json')` | Required | Not needed |
| Build `JsonResponse::fromJsonString(...)` | Required | Not needed |
| Set HTTP status code | Manual on Response | `#[Serialize(code: 201)]` |
| Add custom headers | `$response->headers->set(...)` | `#[Serialize(headers: [...])]` |
| Pass serializer context | Extra argument to `serialize()` | `#[Serialize(context: [...])]` |
| Negotiate response format (json/xml/etc.) | Manual `$request->getRequestFormat()` switch | Automatic from request format |

The attribute is **additive** - controllers that build `Response` manually keep working.

## 2. Before / After

**Pre-8.1 - manual serialization (the boilerplate the attribute eliminates):**

```php
namespace App\Controller;

use App\Model\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class GetUserController
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $data       = new User(1, 'Jane Smith', '...');
        $serialized = $this->serializer->serialize($data, 'json');

        return JsonResponse::fromJsonString($serialized, JsonResponse::HTTP_OK);
    }
}
```

**Symfony 8.1 - same behaviour with `#[Serialize]`:**

```php
namespace App\Controller;

use App\Model\User;
use Symfony\Component\HttpKernel\Attribute\Serialize;

final readonly class GetUserController
{
    #[Serialize]
    public function __invoke(): User
    {
        return new User(1, 'Jane Smith', '...');
    }
}
```

The constructor, the serializer call, the `JsonResponse` construction - all gone. The action signature now states what it really does: return a `User`.

## 3. Full Customization Example

```php
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class CreateProductController
{
    #[Serialize(
        code   : 201,
        headers: ['X-Custom-Header' => 'abc'],
        context: [DateTimeNormalizer::FORMAT_KEY => 'd.m.Y H:i:s'],
    )]
    public function __invoke(): ProductCreated
    {
        // ... create the product

        return new ProductCreated(101);
    }
}
```

This action:
- Returns HTTP `201 Created`
- Adds `X-Custom-Header: abc` to the response
- Formats any `\DateTimeInterface` properties of `ProductCreated` as `d.m.Y H:i:s`

## 4. Parameter Reference

| Parameter | Type | Default | Purpose |
|---|---|---|---|
| `code` | `int` | `200` | HTTP status code on the response |
| `headers` | `array<string, mixed>` | `[]` | Extra HTTP headers set on the response |
| `context` | `array<string, mixed>` | `[]` | Serializer context passed verbatim to `SerializerInterface::serialize()` (groups, datetime format, callbacks, etc.) |

## 5. Format Negotiation

The output format is derived from the request, NOT hardcoded. Route patterns like:

```yaml
# config/routes.yaml
product_show:
    path: /products/{id}.{_format}
    controller: App\Controller\GetProductController
    requirements:
        _format: 'json|xml'
```

… make `#[Serialize]` produce:
- `Content-Type: application/json` for `GET /products/42.json`
- `Content-Type: application/xml` for `GET /products/42.xml`

If a request asks for a format the Serializer cannot produce, the framework auto-returns `415 Unsupported Media Type` - no controller code involved.

Default (no `_format` in the route): JSON.

## 6. What to Return

`#[Serialize]` works on the controller's RETURN VALUE. Allowed return types:

- A single object - DTOs, models, view models
- An array (associative or list)
- `null` (serialized as `null` or empty body, format-dependent)
- A `Generator` / iterable that the Serializer can walk

**Do NOT** return a `Response`, `JsonResponse`, or anything that's already an HTTP response - `#[Serialize]` would wrap it again. If you need full control of the response object, drop the attribute and build the response by hand (as before).

## 7. Common Patterns

### 7.1. Read endpoint (`200 OK`)

```php
#[Serialize]
public function __invoke(int $id, UserRepository $users): User
{
    return $users->findOrFail($id);
}
```

### 7.2. Create endpoint (`201 Created`)

```php
#[Serialize(code: 201, headers: ['Location' => '/users/%d'])]
public function __invoke(#[MapRequestPayload] CreateUserInput $input, UserService $service): User
{
    return $service->create($input);
}
```

(Note: `headers` here is illustrative - `Location` typically needs the new resource ID, which requires building the response manually or using API Platform for proper URL generation.)

### 7.3. Update endpoint with serializer context

```php
#[Serialize(context: ['groups' => ['user:read', 'user:detail']])]
public function __invoke(int $id, #[MapRequestPayload] UpdateUserInput $input, UserService $service): User
{
    return $service->update($id, $input);
}
```

### 7.4. Delete endpoint (`204 No Content`)

```php
#[Serialize(code: 204)]
public function __invoke(int $id, UserService $service): null
{
    $service->delete($id);
    return null;
}
```

### 7.5. Paginated collection

```php
#[Serialize(context: ['groups' => ['user:read']])]
public function __invoke(#[MapQueryString] UserListQuery $query, UserRepository $users): array
{
    return [
        'items' => $users->paginate($query->page, $query->perPage),
        'total' => $users->count($query->filters),
    ];
}
```

## 8. Relation to API Platform (Dockraft stub context)

The Dockraft Symfony stub uses **API Platform 4.3** (`config/packages/api_platform.yaml`, all `src/ApiResource/*` resources). API Platform has its OWN serialization pipeline (state providers/processors, normalization context, IRIs, JSON-LD/Hydra). `#[Serialize]` is for **non-API-Platform** controllers.

| Endpoint type | Tool | Why |
|---|---|---|
| Resource-shaped CRUD under `/v1/...` | API Platform `#[ApiResource]` | Auto OpenAPI docs, JSON-LD, IRI resolution, pagination, filters |
| Ad-hoc HTTP endpoint (webhook receiver, custom command-style RPC, internal admin) | `#[Serialize]` on a plain controller | Lighter, no resource machinery |
| Static HTML/Twig response | `$this->render(...)` - no `#[Serialize]` | Twig templates handle output |

Do NOT add `#[Serialize]` to an API Platform state-provider/processor - those return objects that API Platform already serializes; double-serialization would either error or produce wrapped nonsense.

## 9. Anti-patterns

- **Returning a `Response` from an action that has `#[Serialize]`** - undefined wrapping behaviour. Pick one mechanism.
- **`#[Serialize]` on a controller that uses `$this->render(...)`** - Twig returns a `Response`; collides with attribute. Drop the attribute for Twig actions.
- **Manually injecting `SerializerInterface` together with `#[Serialize]`** - the attribute already uses the serializer. The constructor injection becomes dead weight.
- **Putting groups in the action body instead of `context`** - `context: ['groups' => [...]]` keeps the serialization config declarative and visible at the method signature. Don't pull it into the action body.
- **`#[Serialize]` on an action that needs streaming output** - the attribute fully buffers + serializes. For streaming, build `StreamedResponse` manually.
- **`#[Serialize]` without `code` for a 201/204 endpoint** - defaults to 200, which is semantically wrong for create/delete actions. Always set `code` for non-200 responses.

## 10. Requirements

- `symfony/serializer-pack` (or at minimum `symfony/serializer`) must be installed and registered in `bundles.php` - the framework reads from it when the attribute fires.
- The Dockraft stub installs `symfony/serializer-pack` transitively via API Platform; no extra `composer require` is needed if API Platform is enabled.

## 11. Migration Notes

There is no migration to perform. Existing controllers keep working. Adopt incrementally:

1. For new controllers, start with `#[Serialize]` and a typed return.
2. Refactor old "inject serializer + return JsonResponse" controllers one at a time - only when you're already touching them for another reason.
3. Leave API Platform `ApiResource` classes alone - they're a different abstraction layer.
