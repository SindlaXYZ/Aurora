# Aurora Bundle — Agent Instructions

## Project Overview

`sindla/aurora` is a **Symfony 8.1 bundle** (PHP 8.4+) published on Packagist.
Namespace: `Sindla\Bundle\AuroraBundle`
License: MIT
Branch convention: `8.1` (development), `6.1` (main/stable)
Packagist: `composer require sindla/aurora:8.1.*`

## Code Style

- UTF-8 encoding, LF line endings.
- 4-space indentation; trim trailing whitespace.
- Maximum 199 characters per line.
- All source code, comments, and git metadata (branch names, commit messages, tags) must be written in **English**.
  - Wrong: `codex/gaseste-si-repara-un-bug-u6qdzr`
  - Correct: `codex/find-and-fix-a-bug-u6qdzr`
- PSR-4 autoloading under `Sindla\Bundle\AuroraBundle` mapped to `src/`.
- No `composer.lock` in git — ever.

## Directory Structure

```
src/
├── AuroraBundle.php                  # Bundle entry point
├── Attribute/                        # PHP attributes (FormElement)
├── Command/                          # Symfony console commands
│   ├── PHPUnitCommand.php            # aurora:php-unit — generates SVG badges
│   ├── ComposerCommand.php
│   ├── LazyEntityCommand.php
│   ├── CloudflareR2Command.php
│   └── Middleware/CommandMiddleware.php
├── Composer/ScriptHandler.php        # postInstall / postUpdate hooks
├── Config/AuroraConstants.php
├── Console/SymfonyStyleFactory.php
├── Controller/
│   ├── BlackHoleController.php       # Catches unmatched routes
│   ├── PWAController.php             # Progressive Web App endpoints
│   ├── CompiledController.php
│   └── TestController.php
├── DependencyInjection/              # Extension, Configuration, ExtraLoader
├── Doctrine/
│   ├── DQL/MySQL/                    # MySQL DQL functions
│   ├── DQL/PostgreSQL/               # PostgreSQL DQL functions
│   ├── Migrations/Factory/           # MigrationFactoryDecorator (container-aware)
│   └── TypeHint/
├── Entity/SuperAttribute/
│   ├── ECommerce/                    # Amount, Price, Discount traits (Card, Cash, BankTransfer)
│   ├── Identifiable/                 # ID traits (BigInt, Int, various strategies)
│   ├── Misc/                         # Meta, Searchable, SearchableContent traits
│   └── Timestampable/                # Created, Updated, Deleted, Suspended, etc.
├── Entity/SuperClass/                # Abstract entity base classes
├── Enum/DayOfWeek.php
├── EventSubscriber/
│   ├── BlackHoleSubscriber.php
│   ├── LocaleSubscriber.php
│   ├── OutputSubscriber.php          # HTML minifier + response header injection
│   ├── OwnableSubscriber.php
│   └── SoftDeleteIndexSubscriber.php # Doctrine soft-delete index management
├── Repository/Traits/BaseRepository.php
├── Resources/
│   ├── config/services.yaml          # Bundle service definitions
│   ├── config/routes/routes.yaml
│   └── schema/packages/aurora.yaml  # Reference config template for host apps
├── Tests/Trait/                      # Reusable test traits (DatabaseManagement, Persistence)
├── Utils/
│   ├── AuroraArray/AuroraArray.php
│   ├── AuroraCalendar/               # Calendar utilities
│   ├── AuroraCalendarLinkGenerator/  # Calendar link generation
│   ├── AuroraChronos/AuroraChronos.php  # Date/time handling
│   ├── AuroraClient/AuroraClient.php    # HTTP client + MaxMind GeoIP2
│   ├── AuroraCookiesExtractor/
│   ├── AuroraCryptor/AuroraCryptor.php  # AES encryption (AES-128-CTR default)
│   ├── AuroraHelper/
│   ├── AuroraIO/AuroraIO.php         # File/directory I/O — use this
│   ├── AuroraIP/AuroraIP.php         # IP utilities + KnownBotsAndCrawlers
│   ├── AuroraMatch/AuroraMatch.php   # Pattern matching
│   ├── AuroraPHPUnitCodeCoverageBadge/  # SVG badge generation
│   ├── Calculus/                     # Math (2D, 3D, Geo, Graph via traits)
│   ├── CloudflareR2/CloudflareR2.php # Cloudflare R2 via AWS S3 SDK
│   ├── Diacritics/                   # Diacritic removal (Romanian extension)
│   ├── Git/Git.php
│   ├── IO/IO.php                     # DEPRECATED — use AuroraIO instead
│   ├── Monolog/                      # HtmlFormatter, MiscProcessor
│   ├── PWA/PWA.php                   # Progressive Web App logic
│   ├── PseudoLocalization/
│   ├── Sanitizer/Sanitizer.php
│   ├── Strink/Strink.php             # Fluent string manipulation
│   └── Twig/UtilityExtension.php     # Twig global `aurora` object
└── templates/                        # Twig templates (PWA, error, manifest, offline)

tests/
├── bootstrap.php                     # Standalone-safe bootstrap with stubs
├── Command/, Console/, Controller/
├── Entity/, EventListener/, EventSubscriber/
├── Utils/                            # Mirrors src/Utils/
└── RequirementsTest.php
```

## Key Rules

### Deprecated Code
- `Utils/IO/IO.php` is **deprecated** since 2026-04-21. Use `Utils/AuroraIO/AuroraIO.php` instead.

### PHPUnit
- Use `#[DataProvider('methodName')]` attribute only. Do NOT use `@dataProvider` annotation.
- After modifying any PHP class/method/trait, run all existing PHPUnit tests for that file and finish only when they pass.
- `composer.lock` must never be committed.
- For coverage reports, replace `--no-coverage` with `--coverage-clover coverage.xml`.

### Static Analysis
- PHPStan level 6 for CI (`AGENTS.md` command).
- Local `phpstan.neon` is configured at level 8.

## Dependencies

### Runtime (require)
| Package | Version | Purpose |
|---|---|---|
| `php` | >=8.4 | Runtime |
| `aws/aws-sdk-php` | ^3.368 | CloudflareR2 |
| `brick/math` | ^0.17 | Arbitrary precision |
| `doctrine/orm` | ^3.6 | ORM |
| `firebase/php-jwt` | ^7.0 | JWT |
| `geoip2/geoip2` | ^3.3 | MaxMind GeoLite2 |
| `matthiasmullie/minify` | ^1.3 | HTML/CSS/JS minification |
| `scienta/doctrine-json-functions` | ^6.5 | JSON DQL functions |
| `symfony/uid` | ^8.1 | UUID |
| `symfony/twig-bundle` | ^8.1 | Twig |
| `tedivm/jshrink` | ^1.8 | JS minification |

### Dev (require-dev)
| Package | Version |
|---|---|
| `dama/doctrine-test-bundle` | ^8.6 |
| `phpstan/phpstan` | ^2.1 |
| `phpunit/phpunit` | ^12.4 |

Required PHP extensions: `bcmath`, `curl`, `ctype`, `gmp`, `iconv`, `intl`, `json`, `mbstring`, `openssl`, `pcre`, `session`, `simplexml`, `tokenizer`, `zend-opcache`.

## Local Development Setup

Aurora is a bundle and requires a host Symfony application to run tests. The bundle itself is NOT installed as a standalone project.

```bash
# 1. Create host Symfony app in a sibling directory
mkdir /workspace/Aurora-installed
cd /workspace/Aurora-installed
yes | composer create-project symfony/skeleton:8.1.x-dev . --no-cache
yes | composer require symfony/webapp-pack -W --no-progress

# 2. Register Aurora as a local path repository
composer config repositories.aurora '{"type":"path","url":"/workspace/Aurora","options":{"symlink":true}}'
yes | composer require sindla/aurora:8.1.x-dev -W --no-progress

# 3. Install dev tools
yes | composer require phpunit/phpunit:^12.4 -W --dev --no-progress
yes | composer require dama/doctrine-test-bundle:^8.6 -W --dev --no-progress
yes | composer require phpstan/phpstan:^2.1 -W --dev --no-progress

# 4. Install Aurora's own dependencies
cd /workspace/Aurora-installed/vendor/sindla/aurora/
composer install
cd /workspace/Aurora-installed

# 5. Clear cache
php bin/console cache:clear --env=dev
```

**All code changes must be made inside `/workspace/Aurora-installed/vendor/sindla/aurora/` — only add those files to git.**

## Running Tests

```bash
# Run all tests (no coverage)
KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpunit \
  --no-coverage \
  -c /workspace/Aurora-installed/vendor/sindla/aurora/phpunit.xml.dist \
  /workspace/Aurora-installed/vendor/sindla/aurora/tests/

# Run a specific test directory
KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpunit \
  --no-coverage \
  -c /workspace/Aurora-installed/vendor/sindla/aurora/phpunit.xml.dist \
  /workspace/Aurora-installed/vendor/sindla/aurora/tests/Utils/

# Run with coverage
KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpunit \
  --coverage-clover coverage.xml \
  -c /workspace/Aurora-installed/vendor/sindla/aurora/phpunit.xml.dist \
  /workspace/Aurora-installed/vendor/sindla/aurora/tests/

# PHPStan static analysis (level 6)
KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpstan \
  analyse -l 6 \
  /workspace/Aurora-installed/vendor/sindla/aurora/src
```

## CI/CD (GitHub Actions)

Workflow: `.github/workflows/phpunit.yml`, triggers on push to `8.1` and all pull requests.

- **Environment**: Ubuntu 22.04, PHP 8.4, xdebug coverage.
- **Strategy**: Creates a fresh Symfony 8.1 skeleton, installs Aurora as a dev dependency, syncs `src/` and `tests/` into the build, runs tests per-directory, then runs all with coverage.
- **Badges**: PHPUnit passing/coverage/statements SVG badges are auto-generated via `aurora:php-unit` command and committed to `.github/badges/` on version branches (X.Y where X>=7).
- **Auto-merge**: Pull requests from `codex/*` branches are auto-squash-merged after all checks pass.

## Host App Configuration

A host application must create `config/packages/aurora.yaml` — see `src/Resources/schema/packages/aurora.yaml` for the full reference template.

Key parameters:
- `aurora.bundle` — app bundle name (default: `App`)
- `aurora.root` — project root
- `aurora.tmp` — temp directory
- `aurora.resources` — resources directory (MaxMind databases go here)
- `aurora.static` — public static assets
- `aurora.locales` / `aurora.locale` — supported locales
- `aurora.maxmind.license_key` — MaxMind API key (env: `MAXMIND_LICENSE_KEY`)
- `aurora.minify.output` — enable HTML minification (default: `false`)
- `aurora.pwa.*` — Progressive Web App configuration

Routes (add to `config/routes.yaml`):
```yaml
aurora:
    resource: "@AuroraBundle/Resources/config/routes/routes.yaml"
```

Twig (add to `config/packages/twig.yaml`):
```yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/sindla/aurora/src/templates': Aurora
    globals:
        aurora: '@aurora.twig.utility'
```

Composer hooks (add to host `composer.json`):
```json
"post-install-cmd": ["Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postInstall"],
"post-update-cmd":  ["Sindla\\Bundle\\AuroraBundle\\Composer\\ScriptHandler::postUpdate"]
```

## PHP & Symfony Conventions

### 1. Fundamental Code Style (PSR-1, PSR-4, PSR-12)

- **Spacing**: One space after commas and around binary operators (`==`, `&&`, etc.), except the concatenation operator (`.`). Unary operators (`!`, `--`) are attached to the operand.
- **Strict comparison**: Always use `===` and `!==`. Only use loose comparison when intentional type juggling is needed.
- **Yoda conditions**: When comparing a variable against an expression, place the expression on the left: `if (true === $status)`. Applies to `==`, `!=`, `===`, `!==`. Prevents accidental assignment inside conditions.
- **Return statements**: Use `return null;` when the method explicitly returns null; use `return;` for void. Add a blank line before `return` unless it is the only statement in the block.
- **Class structure**:
  - One class per file (exception: unexported private helper classes).
  - `extends` and `implements` on the same line as the class declaration.
  - Properties before methods. Visibility order: `public` → `protected` → `private`.
  - Constructor and test lifecycle methods (`setUp()`, `tearDown()`) must be declared first.
  - Constructor property promotion: each parameter on its own line with a trailing comma (including the last one).

### 2. Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Variables, methods | `camelCase` | `$isReady`, `calculateTotal()` |
| Constants | `SCREAMING_SNAKE_CASE` | `Command::IS_ARRAY` |
| Classes, interfaces, attributes, enums | `UpperCamelCase` | `UserService` |
| Interface names | `…Interface` suffix | `LoggerInterface` |
| Trait names | `…Trait` suffix | `TimestampableTrait` |
| Exception names | `…Exception` suffix | `InvalidArgumentException` |
| Abstract classes | `Abstract…` prefix | `AbstractRepository` |
| Parameters, routes, Twig vars | `snake_case` | `framework.csrf_protection` |

- Type hints: use short forms — `bool` (not `boolean`), `int` (not `integer`), `float` (not `double`).

### 3. Services and Dependency Injection

- The primary service name must equal the Fully Qualified Class Name (e.g., `App\Service\MailerService`).
- When registering multiple services for the same class, use FQCN for the main one and lowercase+underscore aliases for the rest (e.g., `app.custom_mailer`).
- Container parameter names use lowercase and underscores, except env vars read directly via `%env(API_KEY)%`.

### 4. Collection Method Naming (One-to-Many)

When an object has a single primary collection, use generic names: `get()`, `set()`, `has()`, `add()`, `remove()`, `all()`, `clear()`.

When an object has multiple secondary collections, include the element name:
- `getXXXs()` / `addXXX()` / `removeXXX()`
- `setXXX()` — replaces or adds elements.
- `replaceXXX()` — only modifies existing elements; throws an exception for unregistered keys.

### 5. Exceptions and Error Messages

- Use `sprintf()` to embed variables in exception messages. Do not concatenate with `.`.
- No backticks. Wrap option names and variable references in double quotes.
- Messages start with an uppercase letter and end with a period (`.`).
- When including a class name in a message, use `get_debug_type($var)` instead of `$var::class` to handle primitives and anonymous classes correctly.

### 6. PHPDoc

- Add PHPDoc only when it provides information not already expressed by native type hints (e.g., `array<int, string>`, complex union shapes).
- When a type includes `null`, place it last: `@param string|null $name`.
- Group similar annotations together; separate groups with a blank line.
- Omit `@return` if the method returns nothing or `void`.
- Never use single-line PHPDoc (`/** ... */` on one line).

### 7. Doctrine Upsert Pattern

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

- If the entity exists, setters issue an UPDATE; `persist()` on a tracked entity is a no-op.
- If it does not exist, a new object is created and an INSERT is issued.
- Call `flush()` once after all `persist()` calls in a batch, never per entity.
- Prefer `continue` (skip) over upsert when existing data must not be overwritten and the volume is large — avoids unnecessary UPDATEs.

### 8. Deprecations

- PHPDoc: `@deprecated since Symfony X.Y, use AlternativeClass instead.`
- Code: use `trigger_deprecation('vendor/package', 'X.Y', 'The %s class is deprecated, use %s instead.', __CLASS__, Alternative::class);`

## Notable Patterns

- **Doctrine soft-delete**: `SoftDeleteIndexSubscriber` + `TimestampableDeletedNullable` / `TimestampableDeletedNotNullable` traits.
- **Container-aware migrations**: Use `MigrationFactoryDecorator` — see README for service configuration.
- **MaxMind GeoIP2**: Databases are auto-downloaded by Composer hooks when `MAXMIND_LICENSE_KEY`, `SINDLA_AURORA_GEO_LITE2_COUNTRY`, `SINDLA_AURORA_GEO_LITE2_CITY`, `SINDLA_AURORA_GEO_LITE2_ASN` env vars are set.
- **PWA**: Inject `{{ aurora.pwa(app.request) }}` inside `<head>` in Twig layout.
- **HTML Minifier**: Register `OutputSubscriber` as a `kernel.event_listener` for `kernel.response`.
- **Tests with stubs**: `tests/bootstrap.php` provides Monolog and Twig stubs for running tests without all dependencies installed — allows standalone unit testing of utilities.
- **Strink**: Fluent string builder — chain methods, call `->get()` or cast to string for the result.
- **AuroraCryptor**: AES-128-CTR by default; configure via `->setCipher()` before `->encrypt()` / `->decrypt()`.
