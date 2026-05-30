# Symfony ObjectMapper Component

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/object_mapper.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

The ObjectMapper component eliminates boilerplate "DTO -> Entity" / "Entity -> DTO" mapping code. The mapper reads `#[Map]` attributes and copies properties between objects, optionally renaming, transforming, or skipping fields. The base component (`#[Map]`, `ObjectMapperInterface`, conditional mapping, custom mappers) is unchanged from the 7.3 introduction; Symfony 8.1 adds six **additive** features documented in section 9.

The stub registers `symfony/object-mapper` in `symfony.sh::symfony_install_skeleton()`. The interface `Symfony\Component\ObjectMapper\ObjectMapperInterface` is autowired by default - when using ObjectMapper inside Symfony you only need to type-hint it.

Reference scaffolding: `src/Dto/UserInputDto.php`.

## 1. Where DTOs Live

| Layer | Folder | Naming |
|---|---|---|
| Input (request body -> service) | `src/Dto/` | `<Resource>InputDto.php` |
| Output (entity -> API response) | `src/Dto/` | `<Resource>OutputDto.php` |
| Internal (cross-service contracts) | `src/Dto/Internal/` | `<Concept>Dto.php` |

Keep DTOs **flat**, **public-property**, **no validators inside**. Validation belongs on the entity (via `#[Assert\*]`) or on a separate API Platform-level DTO with its own constraint set.

## 2. Basic Mapping

```php
use App\Dto\UserInputDto;
use App\Entity\User;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final readonly class CreateUserHandler
{
    public function __construct(private ObjectMapperInterface $mapper) {}

    public function __invoke(UserInputDto $dto): User
    {
        // Create a fresh User from the DTO.
        return $this->mapper->map($dto, User::class);
    }
}
```

To patch an existing entity (in-place update):

```php
$this->mapper->map($dto, $existingUser);   // mutates $existingUser; does not return a new instance
```

The `map()` second argument is either a class-name string (create a new instance) or an existing object instance (patch in place).

## 3. `#[Map]` Attribute - Reference

```php
use App\Entity\User;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: User::class)]   // class-level: declares the default target
final class UserInputDto
{
    // Same name on both sides -> no attribute needed.
    public string $firstName = '';

    // Different name on the target.
    #[Map(target: 'emailAddress')]
    public string $email = '';

    // Transform on the way in (callable run per property).
    #[Map(transform: 'strtolower')]
    public string $username = '';

    // Skip this property when mapping (still readable on the DTO).
    #[Map(if: false)]
    public ?string $debugTrace = null;

    // Conditional mapping: skipped when the closure returns false.
    #[Map(if: [self::class, 'isPasswordEditable'])]
    public ?string $newPassword = null;

    public static function isPasswordEditable(mixed $value, object $dto): bool
    {
        return null !== $value && '' !== $value;
    }
}
```

The `#[Map]` parameters are `target`, `source`, `transform`, and `if`. The namespace is `Symfony\Component\ObjectMapper\Attribute\Map`.

## 4. Rules

1. **DTO drives the mapping, not the entity.** Place `#[Map(target: User::class)]` on the DTO. The entity stays free of DTO-specific knowledge.
2. **No validation, no flush.** The mapper copies fields. It does NOT validate (call the `validator` service yourself) and does NOT call `EntityManager::persist()` / `flush()` (the handler / controller does).
3. **Relations: explicit only.** The mapper does NOT traverse Doctrine associations automatically. For `User -> Address`, either map them separately and assemble manually, write a custom `MapperInterface` implementation (see section 6), or use the 8.1 nested-property merge / `MapCollection(targetClass:)` features (section 9) where they fit.
4. **Transformers must be deterministic.** They run once per property; side effects (logging, DB lookups) are surprising at call-site and break tests. Stick to pure transforms (`strtolower`, `trim`, custom value-object factory).
5. **Targets are required.** `Map(target: ...)` is mandatory at class level. The mapper will not guess based on naming conventions.
6. **No PHP magic on the target.** Set methods (`setFoo()`) and writeable public properties both work. `__set` and dynamic properties do not - declare each property explicitly on the target class.

## 5. API Platform Integration

When the entity is exposed via API Platform with `input: UserInputDto::class`, API Platform deserialises the request into the DTO; the project then uses ObjectMapper inside a State Processor to convert DTO -> Entity:

```php
final readonly class UserInputProcessor implements ProcessorInterface
{
    public function __construct(
        private ObjectMapperInterface $mapper,
        private EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        $user = $this->mapper->map($data, User::class);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
```

## 6. Custom Mappers (Per-Target Logic)

When per-target logic is too complex for `#[Map]` (e.g. value object construction, conditional defaults, computed fields), implement `Symfony\Component\ObjectMapper\MapperInterface` in `src/Service/Mapper/<Resource>Mapper.php` and register it with `#[Map(target: User::class, mapper: UserMapper::class)]` on the DTO.

Keep these mappers thin: pure data conversion, no DB queries, no event dispatching.

## 7. Testing

```php
use Symfony\Component\ObjectMapper\ObjectMapper;

final class UserInputDtoTest extends TestCase
{
    public function testEmailIsRenamedToEmailAddress(): void
    {
        $dto = new UserInputDto();
        $dto->email = 'foo@example.com';
        $dto->username = 'FOO';

        $user = (new ObjectMapper())->map($dto, User::class);

        self::assertSame('foo@example.com', $user->getEmailAddress());
        self::assertSame('foo', $user->getUsername());
    }
}
```

The `ObjectMapper` default implementation is constructor-free - you can instantiate it directly in unit tests with no container boot.

## 8. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Symfony\Component\ObjectMapper\Exception\MappingException: No target class found on ...` | DTO missing class-level `#[Map(target: ...)]` | Add the attribute at the class level. |
| `Cannot write to property "...": Property does not exist on App\Entity\User` | Entity property name mismatch | Rename the entity property or use `#[Map(target: '<actualName>')]` on the DTO. |
| `Cannot assign string to property ... of type int` | Transform missing | Add `#[Map(transform: ...)]` returning the correct type. |
| Mapper silently skips a property | `#[Map(if: ...)]` returned false | Verify the condition. |
| Transform callable error not surfaced (pre-8.1) | An invalid transform callable was silently tolerated | On 8.1 the mapper throws on an invalid transform callable (section 9.5) - fix the callable. |

## 9. What Changed in Symfony 8.1

Six additive features. Existing 8.0 mapping code keeps working.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| "skip when null" condition | Hand-written callable | `IsNotNull` built-in condition (9.1) |
| `MapCollection` transform target | Element mapping followed each element's own config | `targetClass:` forces a target for every element (9.2) |
| Nested object mapping to the SAME target class | Mapped to a nested object | Properties merged into the parent target instance (9.3) |
| `TargetClass` / `SourceClass` condition argument | A single class | Also accepts an ARRAY of class FQDNs (9.4) |
| Invalid transform callable | Silently tolerated | Throws an exception (9.5) |
| Reverse class map (Entity -> DTO without an attribute on the entity) | Manual configuration | Automatic class-map array derived from `#[Map]` attributes (9.6) |

### 9.1 `IsNotNull` Built-in Condition

A built-in condition usable as the `if:` of a `#[Map]` - maps the property only when its value is not null. Replaces the common hand-written "skip when null" callable.

```php
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;

final class UserInputDto
{
    // Only map $nickname onto the target when it is not null.
    #[Map(if: new IsNotNull())]
    public ?string $nickname = null;
}
```

It lives in the `Symfony\Component\ObjectMapper\Condition\` namespace alongside the existing `SourceClass` / `TargetClass` conditions. Use it instead of a one-off `static fn (mixed $v): bool => null !== $v` when null is the only thing being checked.

### 9.2 `targetClass` on `MapCollection`

`MapCollection` maps every element of a collection property. In 8.1 its `targetClass:` option forces each element to map to a specific class, regardless of any mapping configuration defined on the source element class.

```php
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

#[Map(target: OrderTarget::class)]
class OrderSource
{
    #[Map(transform: new MapCollection(targetClass: LineItemTarget::class))]
    public array $items = [];
}
```

Each element of `$items` is mapped to `LineItemTarget`. `MapCollection` is in `Symfony\Component\ObjectMapper\Transform\`.

### 9.3 Nested-Property Merge When Targeting the Same Class

When a source property holds another object that maps to the SAME target class as its parent, 8.1 merges that nested object's properties into the current target instance instead of producing a nested object. This flattens nested DTO structures into a single resource.

```php
#[Map(target: User::class)]
class UserInput
{
    public string $name = '';

    // UserAddressInput also maps to User -> its street/city are merged onto the same User.
    public ?UserAddressInput $address = null;
}

#[Map(target: User::class)]
class UserAddressInput
{
    public string $street = '';
    public string $city = '';
}
```

Mapping a `UserInput` produces ONE `User` carrying `name`, `street`, and `city`. Use this only when the nested object genuinely targets the same class; otherwise map it to its own target.

### 9.4 Class-FQDN Arrays for `TargetClass` / `SourceClass`

The `TargetClass` and `SourceClass` conditions accept an ARRAY of class names, matching when the object is an instance of ANY of them.

```php
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;

#[Map(source: 'foo', if: new SourceClass([B::class, C::class]))]
public string $something = '';
```

Use this to apply one mapping rule across several related source / target types without repeating the `#[Map]` per class.

### 9.5 Exception on Invalid Transform Callable

8.1 throws an exception when a `#[Map(transform: ...)]` callable is invalid (not callable, wrong shape), instead of silently tolerating it. No action needed for valid transforms; a previously-masked broken callable now surfaces as a hard error - fix the callable.

### 9.6 Automatic Class-Map from `#[Map]` Attributes

When using ObjectMapper inside the Symfony framework, the reverse class map is derived automatically from the `#[Map]` attributes - you do not configure a `ReverseClassObjectMapperMetadataFactory` by hand. Type-hint `ObjectMapperInterface` and the framework wires the mapping (including the Entity -> DTO direction declared by the target's attributes) for you.

## 10. Anti-patterns

- **Hand-rolling a "skip when null" closure** - use the `IsNotNull` built-in condition (9.1).
- **Letting each collection element follow its own mapping when they must all become one type** - pass `targetClass:` to `MapCollection` (9.2) instead of mapping element-by-element.
- **Forcing a nested object into a separate target when it maps to the parent's class** - rely on the 8.1 nested-property merge (9.3); building the nested object manually re-introduces the boilerplate the merge removes.
- **Repeating the same `#[Map]` rule per source class** - pass an array to `SourceClass` / `TargetClass` (9.4).
- **Wiring a reverse class map by hand inside Symfony** - the framework derives it from `#[Map]` attributes (9.6); manual wiring duplicates it.
- **Putting validation or persistence inside a mapper / transform** - the mapper copies data only; validate and flush in the handler (Rule 2).

## 11. Migration Notes (8.0 to 8.1)

No migration forced - every 8.1 feature is additive. One thing to watch: section 9.5 turns a previously-silent invalid transform callable into a thrown exception, so a latent bug may surface on upgrade. Adopt the rest incrementally:

1. Replace hand-written "skip when null" conditions with `IsNotNull` (9.1).
2. Use `MapCollection(targetClass:)` for collections that must all map to one type (9.2).
3. Flatten nested same-class DTOs via the nested-property merge (9.3) rather than assembling them by hand.
4. Collapse duplicated per-class `#[Map]` rules with array `SourceClass` / `TargetClass` (9.4).
5. Drop any manual reverse-class-map wiring - the framework derives it now (9.6).

## 12. Version Constraints

| Package | Required |
|---|---|
| `symfony/object-mapper` | `^8.1` (for `IsNotNull`, `MapCollection(targetClass:)`, nested-property merge, array `TargetClass` / `SourceClass`, invalid-transform exception, and the automatic class-map; the base `#[Map]` API is carried from `^7.3`) |
