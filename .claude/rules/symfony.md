# Symfony Code Rules and Conventions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-08 | 2026-06-03 |

**Sources:**
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://symfony.com/blog/symfony-8-1-0-released
* https://symfony.com/blog/symfony-8-1-curated-new-features

## 1. Code Style (PSR-1, PSR-4, PSR-12)

- **Syntax and spacing:** Add a single space after commas and around binary operators (`==`, `&&`, etc.), except for the concatenation operator (`.`). Unary operators (`!`, `--`) must be attached to the variable.
- **Strict types:** Use strict comparison (`===`, `!==`), except where type juggling is intentionally required.
- **Comment width:** Comment prose (`//`, `#`, and the text lines inside `/** ... */` docblocks) may run up to 199 characters per line (inclusive, counting indentation + comment markers). Pack words onto each line and break ONLY when the next word would exceed 199, when a new paragraph starts, or at a new docblock tag (`@param`, `@throws`, ...). Do NOT wrap comment prose at ~80-104 characters - artificially short, multi-line comment blocks are the anti-pattern. This governs comment text only, not executable code. See the worked example in `.claude/CLAUDE.md` (PHP Code Style, rule 2).
- **Yoda conditions:** Use Yoda conditions when comparing a variable to an expression (e.g. `if (true === $status)`) to avoid accidental assignment in `if` statements. Applies to `==`, `!=`, `===`, `!==`.
- **Return statements:** Use `return null;` when a method explicitly returns null, and `return;` when the function returns `void`. Add a blank line before `return`, except when it is the only statement in a block.
- **Class structure:**
    - One class per file (exception: private helper classes not exported or instantiated externally).
    - Declare inheritance (`extends`) and interfaces (`implements`) on the same line as the class.
    - Order properties before methods. Visibility order: `public`, `protected`, then `private`. Constructor and test setup methods (`setUp()`, `tearDown()`) must be the first methods.
    - When using constructor property promotion, put each parameter on its own line followed by a comma (including the last parameter).

## 2. Naming Conventions

- **Variables and methods:** Use `camelCase` (e.g. `$isReady`, `calculateTotal()`).
- **Constants:** Use `SCREAMING_SNAKE_CASE` (e.g. `Command::IS_ARRAY`).
- **Classes, interfaces, attributes, and enums:** Use `UpperCamelCase`.
    - Interfaces must have the `Interface` suffix.
    - Traits must have the `Trait` suffix.
    - Exceptions must have the `Exception` suffix.
    - Abstract classes must have the `Abstract` prefix (exception: test cases ending in `TestCase`).
- **Parameters, routes, and Twig variables:** Use `snake_case` (e.g. `framework.csrf_protection`).
- **Type-hinting:** For PHPDoc and type declarations, use the short standardized forms: `bool` (not `boolean`), `int` (not `integer`), `float` (not `double`).

## 3. Services and Dependency Injection

- **Service names:** The name of a primary service must match its Fully Qualified Class Name (FQCN) (e.g. `App\Service\MailerService`).
- **Multiple services:** If you declare multiple services for the same class, use the FQCN for the primary one and lowercase underscore names for the others (e.g. `app.custom_mailer`).
- Container parameter names use lowercase with underscores (exception: reading directly from the environment with the `%env(API_KEY)%` syntax).

## 4. Collection Methods (One-to-Many)

When an object contains a single primary collection (one major relationship), name methods simply: `get()`, `set()`, `has()`, `add()`, `remove()`, `all()`, `clear()`.

When an object has multiple secondary collections, append the element name:
- `getXXXs()` and `addXXX()`
- `removeXXX()`
- **Note:** `setXXX()` replaces or adds new elements. `replaceXXX()` only modifies existing elements and throws an exception if given an unregistered key.

## 5. Exceptions and Error Messages

- **Message interpolation:** Use `sprintf()` to embed variables in exception messages - avoid string concatenation with `.`.
- **No backticks:** Do not use backticks (\`). When referring to options or variable names, wrap them in double quotes (`"variable_name"`).
- **Format:** An error message starts with an uppercase letter and must end with a period (`.`).
- **Runtime types:** When including a class name in a message, use `get_debug_type($var)` instead of `$var::class` to correctly handle primitive types and anonymous classes.

## 6. PHPDoc

- Add PHPDoc blocks only when they add value (e.g. generic arrays such as `array<int, string>`). Do not duplicate information already expressed by native type hints.
- When declaring types that include `null`, put it last (e.g. `@param string|null $name`).
- Group similar annotations. Leave a blank line between different groups (e.g. between `@param` and `@return`).
- Omit the `@return` tag if the method returns nothing or returns `void`. Do not use single-line PHPDoc blocks (`/** ... */`).

## 7. Upsert Pattern (Doctrine)

When inserting or updating an entity based on its existence in the database, use the upsert pattern with inline assignment in the condition:

```php
if (!($entity = $this->repository->findOneBy([...])) instanceof EntityClass) {
    $entity = new EntityClass();
}

$this->em->persist(
    $entity
        ->setField1($value1)
        ->setField2($value2)
);
$this->em->flush();
```

- If the entity exists, setters perform an UPDATE; `persist()` on an already-tracked entity is a no-op.
- If the entity does not exist (`null`), a new object is created and an INSERT is performed.
- Call `flush()` once after all `persist()` calls in a batch - not per entity.
- Prefer skipping (`continue`) over upsert when existing data must not be overwritten and the volume is large - this avoids unnecessary UPDATE queries.

## 8. Deprecations

When suggesting refactoring of code marked for deprecation, use `@deprecated since Symfony [Version], use [Alternative] instead.` in PHPDoc. In the actual code, use `trigger_deprecation()` to alert developers (e.g. `trigger_deprecation('symfony/package', '5.1', 'The %s class is deprecated...', __CLASS__);`).

## 9. API Platform

- State Providers: https://api-platform.com/docs/core/state-providers/
- State Processors: https://api-platform.com/docs/core/state-processors/

## 10. Native SQL Queries

To run a raw SQL query directly against the configured database (without writing PHP), use the Doctrine DBAL `dbal:run-sql` console command:

```bash
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console dbal:run-sql "SELECT * FROM ..."
```

- Useful for ad-hoc inspection, debugging migrations, or verifying data without booting a full request cycle.
- The query is executed against the connection configured in `doctrine.yaml` (uses `DATABASE_URL` from `.env.local`).
- Wrap the SQL in double quotes; escape inner double quotes with a backslash if needed.

## 11. Symfony 8.1 - Notable Features and Changes

This section catalogs the notable features, behavior changes, and deprecations introduced in Symfony 8.1, for use when migrating an existing project to 8.1 or starting fresh on 8.1. Curated from `CHANGELOG-8.1.md` (features, deprecations, and behavior-changing fixes; pure-internal "Various fixes and hardenings" and transparent security patches are omitted). The largest features each have a dedicated rule file - those are referenced, not repeated here. Everything else is documented inline. Every item requires the relevant `symfony/*` component at `^8.1` unless its dedicated rule file states a wider range.

### 11.1 Features with a dedicated rule file

| Feature area | Headline 8.1 additions | Rule file |
|---|---|---|
| Console commands | Method-based commands (`#[AsCommand]` on methods), console argument resolvers (`#[MapEntity]`/`#[MapDateTime]`/service injection), `#[Ask]` + `#[AskChoice]` interactive prompts, `InputFile` image paste, answer + `#[MapInput]` validation, object option defaults, `RawInputInterface` | `symfony-command.md` (commands + resolvers) / `symfony-console-io.md` (input + output) |
| HTTP `#[Cache]` attribute | Conditional `if`, explicit `request`/`args` expression vars, closure `etag`/`lastModified`, repeatable on one action | `cache-attribute.md` |
| `#[Serialize]` attribute | Return plain objects/arrays from controllers; framework serializes + sets status/headers/format | `serialize-attribute.md` |
| Dynamic controller attributes | Runtime-overrideable attributes via `_controller_attributes`, per-attribute kernel events, `ResponseEvent::$controllerMetadata` (a `ControllerArgumentsMetadata`), `ControllerEvent::evaluate()` | `dynamic-controller-attributes.md` |
| Request payload mapping | `UploadedFile` in `#[MapRequestPayload]` DTOs, variadic args, `mapWhenEmpty`, dynamic `validationGroups` (Expression/Closure) | `request-payload-mapping.md` |
| Dependency Injection | Lazy `#[Autowire(env:)]` closures/`Stringable`, `target:` on `#[AsAlias]`, inline `Definition` factory/configurator, service stacks as decorators, `decorates_tag` + `#[AsTagDecorator]`, `exclude:` on `import()`, dotted env-var names, voter ordering via `#[AsTaggedItem]`, bundles as compiler passes | `dependency-injection.md` |
| `DeepCloner` | COW-friendly deep cloning; `Hydrator` + `Instantiator` deprecated in favor of `deepclone_hydrate()` | `deep-cloner.md` |
| HTTP-less apps | `AbstractKernel` + `KernelTrait`, `ServicesBundle` + `ConsoleBundle` split, `#[RequiredBundle]` | `http-less-applications.md` |
| Messenger | `--fetch-size`, `--no-reset=N`, `AmqpPriorityStamp`, `BatchHandlerTrait::getIdleTimeout`, decode-failure routing, listable `RedisReceiver`, `redis_cluster=true`, per-day quorum delay queues, `queues: false`, dedup lock release on failure | `messenger.md` |
| Translation | `LocaleFallbackProvider`, env vars in `enabled_locales`, XLIFF 2.1/2.2 + PGS module, expanded `ChoiceType` placeholder via `translation_domain` | `translation.md` |
| Validator | `Xml` constraint, clock-aware comparison/range validators, `enablePropertyMetadataExistenceCheck()`, reentrant validators (`validateInContext()`) | `validator.md` |
| JSON streaming & querying | JsonStreamer value-object transformers, built-in `DateInterval`/`DateTimeZone`, `date_time_timezone`, default options; JsonPath `#[AsJsonPathFunction]` | `jsonstreamer.md` / `jsonpath.md` |

The remaining Console 8.1 items are documented in the two console rule files - `symfony-command.md` (commands, resolvers, testing) and `symfony-console-io.md` (input + output): the result-based testing API (`runCommand()` returning an `ExecutionResult`, replacing `executeCommand()` in command tests) with the `assertCommandFailed()` / `assertCommandIsInvalid()` helpers (`symfony-command.md` §9); `SymfonyStyle` progress-bar customization and outline-style block methods (`symfony-console-io.md` §2.1); the OSC 9;4 progress-reporting escape sequence (`symfony-console-io.md` §2.2); and the optional PSR container parameter on `Application` (`symfony-console-io.md` §2.3).

### 11.2 HTTP Kernel and controllers (beyond the dedicated files)

- **`#[MapRequestHeader]` (NEW).** Maps a request header straight onto a controller argument, like the payload/query mappers do for body and query string:

  ```php
  use Symfony\Component\HttpKernel\Attribute\MapRequestHeader;

  public function webhook(
      #[MapRequestHeader('X-Signature')] string $signature,
  ): Response {
      // $signature = the value of the X-Signature request header
  }
  ```

- **Typed request-attribute validation.** Route attribute values are validated against the controller's declared parameter types BEFORE the controller runs - a mismatch fails early instead of inside the action.
- **`SOURCE_DATE_EPOCH` support** for reproducible builds.
- **Deprecation:** the `Symfony\Component\HttpKernel\DependencyInjection\Extension` class is deprecated.

### 11.3 Dependency Injection

The 8.1 DI additions (lazy `#[Autowire(env:)]` closures/`Stringable`, `target:` on `#[AsAlias]`, inline `Definition` as factory/configurator, service stacks as decorators, `decorates_tag` + `#[AsTagDecorator]`, `exclude:` on `import()`, dotted env-var names, `#[AsTaggedItem]` voter ordering, bundles as compiler passes) and the three 8.1 DI deprecations are documented in `dependency-injection.md`.

### 11.4 Security (no dedicated v8.1 file)

- **Deprecation:** `erase_credentials` config key, its container parameter, and the `AuthenticatorManager` constructor argument. Aligns with the 7.3 deprecation of `UserInterface::eraseCredentials()` - wipe sensitive data via DTOs at the API boundary or `AuthenticationTokenCreatedEvent` instead.
- `this` is available in `#[IsGranted]` subject expression variables when a subject exists.
- `enforce_key_usage_verification` option on OIDC discovery.
- Retrieval of parent role names from the role hierarchy.
- Enums supported in `SignatureHasher::computeSignatureHash()`.
- Completed `Clear-Site-Data` directives on logout.
- **Behavior change (re-test on upgrade):** HEAD requests no longer bypass the `methods:` filter of `#[IsGranted]`, `#[IsCsrfTokenValid]`, and `#[IsSignatureValid]`. Routes that (accidentally) relied on HEAD slipping past those attributes are now enforced.
- **Do NOT use:** a per-username login rate-limit was added during the 8.1 cycle and then **reverted before release**. It is NOT part of 8.1 - keep using `login_throttling` + the rate-limiter component (see `.claude/rules/rate-limiter.md` in the v8.0 stub for the compound-limiter pattern).

### 11.5 FrameworkBundle (no dedicated v8.1 file)

- `debug:router --sort`; decoration stack shown in `debug:container`.
- Configurable Webhook header names and signing algorithm.
- Custom marshaller configurable per cache pool.
- **Semaphore and flock stores are scoped by project dir by default** - lock keys no longer collide between projects sharing a host (relevant to the stub's `DeduplicateStamp` / lock usage).
- `MicroKernelTrait::$allowedEnvs` to enforce allowed `APP_ENV` values.
- Test helpers: shortcut to run a console command in tests; mock non-shared services in tests.
- Default action configurable in `HtmlSanitizer` config.
- **Deprecations:** `terminate_on_cache_hit` http_cache option; `router.request_context.scheme` / `.host` container parameters; `Bundle::registerCommands()`.

### 11.6 HttpFoundation

- **Deprecation:** setting public properties of `Request` and `Response` objects directly is deprecated - go through the accessors / property bags.
- `BinaryFileResponse::shouldDeleteFileAfterSend()`.
- `SessionHasFlashMessage` test constraint.

### 11.7 HttpClient

- **`CachingHttpClient` default `maxTtl` is now 86400s** - prevents cache items living forever; a behavior change for projects relying on unbounded upstream TTLs (see `.claude/rules/http-client.md` in the v8.0 stub).
- Custom DNS resolution via a decorator; `$allowList` on `NoPrivateNetworkHttpClient`; `max_connect_duration` option; persistent cURL handles; `GuzzleHttpHandler` to use Symfony HttpClient as a Guzzle handler; stale-if-error fallback logging.

### 11.8 Serializer

- `COLLECT_EXTRA_ATTRIBUTES_ERRORS` plus a full deserialization path in errors.
- `AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION` for scalar type transformation.
- Deprecation triggered when a date cannot be parsed with the default format.

### 11.9 ObjectMapper

- `IsNotNull` built-in condition; `targetClass` on `MapCollection` transform; nested properties merged when targeting the same class; class-FQDN arrays accepted as `TargetClass` / `SourceClass`; exception thrown on an invalid transform callable; automatic class-map array derived from `#[Map]` attributes.

### 11.10 Forms

- `DateType` `labels` option for year/month/day sub-field labels; `EntityType` `uid_format` option; `BirthdayType` automatic `attr` in `single_text`; submit forms with unchecked checkboxes in request handlers; `ViolationMapperInterface` injection for the validator extension; `NavigatorFlowType` reset button + `AbstractController::createFormFlowBuilder()`. The expanded `ChoiceType` placeholder fix is in `translation.md`.

### 11.11 JsonStreamer

- Value-object transformers (`ValueObjectTransformerInterface`); built-in `DateInterval` / `DateTimeZone` value objects; `date_time_timezone` conversion option; `framework.json_streamer.default_options`. See `jsonstreamer.md`.

### 11.12 Other components

- **RateLimiter: `#[RateLimit]` attribute (NEW)** for declarative controller rate limiting, plus a calendar-aligned mode for `FixedWindowLimiter`:

  ```php
  use Symfony\Component\HttpKernel\Attribute\RateLimit;

  #[RateLimit(limiter: 'contact_form')]
  public function submit(): Response { /* ... */ }
  ```

- **Tui: a NEW component** for building terminal user interfaces.
- **Uid:** `Uuid47Transformer` for UUIDv7 <-> v4 conversion; `$format` argument on `Ulid::isValid()`.
- **ExpressionLanguage:** null-safe syntax for array access.
- **PropertyInfo:** support for property-hook settable types.
- **TypeInfo:** resolves tentative return types and object shapes.
- **VarDumper:** dumps class-strings as class stubs with source location and static properties; CSP nonce support in `HtmlDumper`.
- **TwigBundle:** `twig.safe_class` resource tag to register classes whose `__toString()` is pre-escaped - same tag documented in the v8.0 `twig.md` (requires Symfony 8.1+).
- **CssSelector:** `:has()` support.
- **DomCrawler:** add choices to a single `<select>`.
- **Notifier:** Prelude bridge; Telegram local API server support.
- **Mailer:** SES port and tls options; SendGrid `send_at` scheduled delivery; Infobip `ipPoolId`.
- **Semaphore:** a lock-based store and a `SemaphoreKeyNormalizer`.
- **Scheduler:** `debug:scheduler` can order recurring messages by next run date.
- **PasswordHasher:** `security:hash-password` reads from stdin.
- **Workflow:** dump listeners in Graphviz diagrams.
- **WebLink:** new `Link::AS_*` constants for `rel=preload` / `rel=modulepreload`.
- **Filesystem:** `Filesystem::mirror()` option `copy_on_windows` renamed to `follow_symlinks`.
- **Contracts:** new `ContainerAwareInterface`; hooked-property support in `ServiceMethodsSubscriberTrait`.
- **JsonPath:** custom functions in filter expressions via `#[AsJsonPathFunction]` (with `FunctionReturnType`). See `jsonpath.md`.
- **Runtime (FrankenPHP):** `FRANKENPHP_RESET_KERNEL` to reset the kernel between requests; `FrankenPhpWorkerResponseRunner` for simple response return; `SymfonyRuntime::resolveType()` for customizing type resolution in extending runtimes.
- **ErrorHandler:** `@method` deprecation notices triggered for abstract classes; namespace remapping in `DebugClassLoader` to relax the same-vendor constraint.
- **MonologBridge:** `$subjectMaxLength` option on `MailerHandler`.
- **Finder:** can set the `UNIX_PATH` flag when recursing directories.

### 11.13 Deprecations introduced in 8.1 (migration checklist)

When migrating an existing project onto 8.1, stop using the following (each still works in 8.1 but is scheduled for removal):

- **HttpKernel:** the `Extension` class.
- **HttpFoundation:** direct public-property writes on `Request` / `Response`.
- **Security:** `erase_credentials` config + container parameter + `AuthenticatorManager` argument.
- **DependencyInjection:** named autowiring aliases without `#[Target]`; default index/priority methods for tagged locators/iterators; invalid options on `from_callable`.
- **FrameworkBundle:** `terminate_on_cache_hit` http_cache option; `router.request_context.scheme` / `.host` parameters; `Bundle::registerCommands()`.
- **VarExporter:** `Hydrator` + `Instantiator` (use `deepclone_hydrate()` - see `deep-cloner.md`).
- **Messenger:** `StopWorkerOnTimeLimitListener` (use the `time_limit` worker option).
- **DoctrineBridge:** `RegisterMappingsPass::$aliasMap`.
- **Filesystem:** `mirror()` option `copy_on_windows` (renamed `follow_symlinks`).
- **Serializer:** unparseable-date-with-default-format now triggers a deprecation.

### 11.14 Migration checklist (from 7.x / 8.0 to 8.1)

1. Audit the deprecations in 11.13 and replace each before they are removed in 9.0.
2. Re-test any route that received HEAD requests against `#[IsGranted]` / `#[IsCsrfTokenValid]` / `#[IsSignatureValid]` (11.4 behavior change).
3. Re-check HttpClient caching expectations - the `CachingHttpClient` default `maxTtl` is now 86400s (11.7).
4. Do not adopt the per-username login rate-limit - it was reverted and is not in 8.1 (11.4).
5. For new code, prefer the new attributes where they fit: `#[MapRequestHeader]`, `#[RateLimit]`, and the features in the dedicated rule files (11.1).
