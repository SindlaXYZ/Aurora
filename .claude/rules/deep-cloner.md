# `DeepCloner` - Deep Object Graph Cloning

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-12 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-deep-cloner
* https://symfony.com/doc/8.1/components/var_exporter.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/var-exporter/blob/8.1/DeepCloner.php
* https://github.com/symfony/var-exporter/blob/8.1/Instantiator.php
* https://github.com/symfony/var-exporter/blob/8.1/Hydrator.php

`Symfony\Component\VarExporter\DeepCloner` recursively clones an entire object graph (nested objects, arrays, properties) while preserving copy-on-write semantics for strings and arrays. It replaces the long-standing `unserialize(serialize($value))` idiom with a measurably faster path and deprecates the `Instantiator` + `Hydrator` classes from the same component.

## 1. What Changed in Symfony 8.1

`DeepCloner` is a **new class** in `symfony/var-exporter`. There is no pre-8.1 first-class equivalent - projects either used native `clone` (shallow), `unserialize(serialize(...))` (slow), or hand-written recursive cloners.

| Concern | Pre-8.1 | Symfony 8.1 |
|---|---|---|
| Shallow clone (top-level only, nested refs shared) | `$copy = clone $obj;` | `clone` still available - for shallow cases |
| Deep clone of full graph | `$copy = unserialize(serialize($obj));` (slow, allocates a temporary string) | `DeepCloner::deepClone($obj)` |
| Build a prototype factory | DIY closure or factory class | `$cloner = new DeepCloner($prototype); $cloner->clone();` (repeatedly) |
| Instantiate without constructor + hydrate | `Instantiator::instantiate() + Hydrator::hydrate()` (now **deprecated**) | `deepclone_hydrate($class, $data)` |
| Performance vs serialize trick | baseline | 4×-15× faster + ~30-40% smaller payload + lower memory |

## 2. Basic Usage

```php
use Symfony\Component\VarExporter\DeepCloner;

$clone = DeepCloner::deepClone($originalObject);
```

That's the entire API for the simple case. The returned object is a fully independent copy - mutating `$clone->user->name` does NOT affect `$originalObject->user->name`.

Every entry point accepts an optional `?array $allowedClasses` argument - `DeepCloner::deepClone(mixed $value, ?array $allowedClasses = null)`, `new DeepCloner(mixed $value, ?array $allowedClasses = null)`, `clone(?array $allowedClasses = null)`, and `cloneAs(string $class, ?array $allowedClasses = null)`. When a non-null list is passed, only objects of those classes are cloned and any other object in the graph is shared by reference instead of copied. Leave it `null` (the default) to deep-clone the whole graph.

## 3. Prototype Cloner (repeated cloning)

When you clone the same prototype many times (e.g., spawning fresh DTO instances per request, per fixture row, per worker task), instantiate the cloner once and reuse:

```php
$cloner = new DeepCloner($prototype);

$clone1 = $cloner->clone();
$clone2 = $cloner->clone();
```

The cloner analyses the prototype's object graph upfront and amortises that cost across all subsequent `clone()` calls. Use this when you'll produce more than ~2 clones from the same source.

## 4. `cloneAs()` - Clone Into a Compatible Class

Clones the prototype's state into a different (but structurally compatible) class. Useful for promoting / demoting class hierarchies without serializing:

```php
$childDefinition = (new DeepCloner($definition))
    ->cloneAs(ChildDefinition::class);
```

`ChildDefinition::class` must be a class compatible with the source - same property layout, or a subclass. Symfony itself uses this internally for `Definition` → `ChildDefinition` promotion in the DI container compiler.

## 5. Export / Import (`toArray()` / `fromArray()`)

The cloner can serialize its internal state to an array - useful for caching, cross-process transport, or persisting prototypes:

```php
$payload = (new DeepCloner($graph))->toArray();
$json    = json_encode($payload);
// ... store, cache or send the payload ...
$clone   = DeepCloner::fromArray(json_decode($json, true))->clone();
```

This is ~30-40% smaller than `serialize($graph)` and survives JSON encoding.

## 6. `deepclone_hydrate()` - Instantiator + Hydrator Replacement

Two classes are **deprecated** in 8.1:
- `Symfony\Component\VarExporter\Instantiator`
- `Symfony\Component\VarExporter\Hydrator`

Their combined functionality (instantiate without constructor + hydrate from array) is now a single function call:

```php
// Before (deprecated in 8.1):
$user = Instantiator::instantiate(User::class);
Hydrator::hydrate($user, ['name' => 'Alice']);

// After:
$user = deepclone_hydrate(User::class, ['name' => 'Alice']);
```

`deepclone_hydrate()` is a **global function**, not a method on `DeepCloner`. It is NOT defined by `symfony/var-exporter` itself: the function is provided either by the native `deepclone` PHP extension or by the `symfony/polyfill-deepclone` userland package when the extension is not loaded. The deprecation messages on `Instantiator` and `Hydrator` both read "use deepclone_hydrate() from the deepclone extension instead". It skips the constructor entirely (useful for DTOs hydrated from external sources, fixtures, JSON, etc.).

## 7. Optional PHP Extension for Native Speed

The three global functions `deepclone_hydrate()`, `deepclone_to_array()`, and `deepclone_from_array()` come from one of two sources:
- the native `deepclone` PHP extension (C implementation - fast path), or
- the `symfony/polyfill-deepclone` package (userland fallback when the extension is absent).

`DeepCloner` calls `deepclone_to_array()` / `deepclone_from_array()` internally for its `toArray()` / `fromArray()` paths, so one of the two sources must be installed. The blog post refers to the extension package as `symfony/php-ext-deepclone`; whichever name the PECL build uses, when the extension is loaded `DeepCloner` transparently uses it instead of the polyfill. No code changes needed - just install and load.

For Dockraft builds, this extension is OPTIONAL. Only install it if profiling shows `DeepCloner` is a hot path in your application (rare outside fixture-heavy test suites or hydration-heavy ETL workers).

## 8. Behaviour vs Native `clone` and `__clone()`

| Aspect | `clone $obj` | `DeepCloner::deepClone($obj)` |
|---|---|---|
| Top-level object | new instance | new instance |
| Nested objects | **shared references** with original | recursively cloned (independent) |
| Arrays of objects | shallow - array is new, elements shared | deep - array AND elements are independent |
| Strings / scalars | copy-on-write | copy-on-write |
| `__clone()` magic method | called on the new instance | called on every cloned object in the graph |
| Speed | very fast | fast (optimised for deep walks); slower than shallow `clone` |

Use native `clone` when you only need an independent top-level object and you'll either replace nested references or you're certain mutations on nested objects are intended. Use `DeepCloner` whenever the consumer must NOT be able to affect the original through any nested reference.

## 9. Use Cases (and when NOT to use it)

### Use `DeepCloner` for

- **Audit snapshots** - capture an entity's full state before changes for diff/audit logging without DB roundtrip
- **Fixture cloning** - generate many `User` instances from a single template in tests
- **DTO defensive copies** - prevent downstream code from mutating shared input objects
- **DI container** - Symfony itself uses it for service definition cloning at compile time
- **Form data snapshots** - preserve "submitted but not yet applied" state across request boundaries

### Do NOT use `DeepCloner` for

- **Doctrine managed entities** - the cloned entity is detached and DOES NOT share identity with the original. Persisting the clone produces a duplicate row, not an update. Use Doctrine's own `EntityManager::merge()` or `clone` + manually reset `id` for intentional duplication. The article does NOT cover Doctrine specifics; the safest assumption is the same as for `unserialize(serialize($entity))` - proxies and lazy associations may behave unexpectedly.
- **Objects holding open resources** (file handles, DB connections, sockets) - the resource isn't truly cloneable. Same caveat as native `clone`.
- **Static-state-holding classes** - `DeepCloner` won't clone static properties; static state is shared.
- **Trivially shallow cases** - if you only need to replace a single property, native `clone` is faster and clearer.

## 10. Anti-patterns

- **`DeepCloner::deepClone()` in a tight loop instead of `new DeepCloner($prototype); clone() x N`** - the prototype cloner is dramatically faster when cloning N times from the same template.
- **Replacing valid `clone $obj` usages without measurement** - `clone` is faster for shallow cases. Adopt `DeepCloner` where a real bug or test-isolation issue exists.
- **Using `deepclone_hydrate()` for objects with non-trivial constructors that perform validation** - the constructor is skipped, so invariants enforced in `__construct()` are bypassed. Use this only for plain data containers (DTOs, value objects without validation, ORM proxies).
- **Storing `DeepCloner::toArray()` output without versioning** - the array format is tied to the cloner's internal representation. Pin the Symfony version that wrote the payload before decoding.

## 11. Migration Notes (8.0 → 8.1)

Two pieces of work, both incremental:

1. **Replace `unserialize(serialize($obj))` calls** with `DeepCloner::deepClone($obj)`. Same semantics, faster. Touch only sites with measurable cost or known correctness issues.
2. **Replace `Instantiator::instantiate() + Hydrator::hydrate()`** with `deepclone_hydrate($class, $data)`. Both old classes are `@deprecated` in 8.1 - they still work but will be removed in 9.0.

No new mandatory work. Native `clone` and `__clone()` semantics are unchanged.

## 12. Quick Reference

| API | Returns | When to use |
|---|---|---|
| `DeepCloner::deepClone($obj)` | independent deep copy | One-off clone |
| `new DeepCloner($p); $p->clone()` | independent deep copy | Repeated cloning of same prototype |
| `(new DeepCloner($p))->cloneAs(Subclass::class)` | instance of `Subclass` with `$p`'s state | Class promotion/demotion |
| `(new DeepCloner($p))->toArray()` | serializable array | Cache / transport a prototype |
| `DeepCloner::fromArray($arr)` | cloner instance | Restore from `toArray()` payload |
| `deepclone_hydrate($class, $data)` | `$class` instance hydrated from `$data` | Replace `Instantiator + Hydrator` |
