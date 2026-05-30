# Symfony Console Commands

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-08 | 2026-05-30 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-method-based-commands
* https://symfony.com/blog/new-in-symfony-8-1-console-argument-resolvers
* https://symfony.com/blog/new-in-symfony-7-4-improved-invokable-commands
* https://symfony.com/doc/8.1/console.html
* https://symfony.com/doc/8.1/console/value_resolver.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/ArgumentResolver/ValueResolver/ValueResolverInterface.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Attribute/Reflection/ReflectionMember.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Attribute/ValueResolver.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Attribute/AsTargetedValueResolver.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Attribute/Interact.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Tester/ExecutionResult.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Tester/ConsoleAssertionsTrait.php

This rule documents the Symfony 8.1 console command styles (legacy `execute()`, invokable `__invoke()`, method-based) and how they differ from Symfony 8.0, plus the 8.1 console argument resolvers (section 6) and the result-based testing API (section 9). It also covers how to treat the v8.0 stub's existing command code on 8.1 - the legacy `CommandMiddleware` pattern, the `#[Interact]` method attribute, and the `InvokableExampleCommand` reference scaffolding (section 8). All command styles coexist - the older styles remain fully supported and are NOT deprecated.

Console INPUT (interactive prompts, answer/object validation, file input, raw-input forwarding) and OUTPUT (`SymfonyStyle` blocks + progress, markdown tables, tree rendering) are documented in the companion rule **`symfony-console-io.md`** - this file was split from it to stay under the rule-file size limit. The two together cover all console content; new console material belongs in one of these two files (never a new per-feature console file).

## 1. Style Matrix - Quick Reference

| Style | Available since | Marker | Class extends `Command`? | Methods per class | When to use |
|-------|-----------------|--------|--------------------------|-------------------|-------------|
| Legacy `execute()` | < 7.3 | `#[AsCommand]` on class (or static `$defaultName`) | Yes (`extends Command`) | One `execute()` method | Existing code; commands needing `initialize()` / `interact()` lifecycle hooks |
| Invokable `__invoke()` | 7.3 | `#[AsCommand]` on class | No | One `__invoke()` method | Single-purpose commands (preferred for new code in 8.0) |
| Method-based | **8.1 (new)** | `#[AsCommand]` on **each method** | No | Many - one per `#[AsCommand]` method | Grouping closely related commands that share dependencies |

## 2. What Changed in Symfony 8.1

**Before 8.1 (Symfony 8.0 and earlier):** `#[AsCommand]` could only be placed on a **class**. One class = one command. Grouping `app:user:create`, `app:user:delete`, `app:user:promote` required three separate classes, each with its own constructor wiring the same `UserRepository` and `LoggerInterface`.

**Since 8.1:** `#[AsCommand]` may also be placed on **public methods**. Each annotated method is registered as an independent command via autoconfiguration. The class itself is a regular service - it does NOT extend `Command` - and all methods share the constructor's injected dependencies. This mirrors the controller pattern (one controller class, many action methods).

**Backward compatibility:** All previous styles still work in 8.1. Method-based commands are an additive feature, not a migration target.

## 3. Examples

### 3.1. Legacy `execute()` Style (Symfony 8.0 and Earlier)

This is the style used in `src/Command/Dev/DemoCommand.php` from the v8.0 stub.

```php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'app:user:create',
    description: 'Creates a new user'
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username of the user')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Promote to admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $isAdmin  = $input->getOption('admin');

        // ...

        return Command::SUCCESS;
    }
}
```

Verbose. Required for any command that needs `initialize()` or `interact()` lifecycle hooks (which only the legacy style supports).

### 3.2. Invokable `__invoke()` Style (Symfony 7.3+)

The class no longer extends `Command`. The single `__invoke()` method receives arguments and options directly as typed parameters via `#[Argument]` and `#[Option]` attributes.

```php
namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:create', description: 'Creates a new user')]
final class CreateUserCommand
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'The username of the user')]
        string $username,

        #[Option(description: 'Promote to admin', shortcut: 'a')]
        bool $admin = false,

        OutputInterface $output,
    ): int {
        // ...
        $output->writeln(sprintf('Created %s (admin: %s)', $username, $admin ? 'yes' : 'no'));

        return Command::SUCCESS;
    }
}
```

Drops `configure()`, drops manual `$input->getArgument()` / `$input->getOption()` calls, drops the `Command` base class. Recommended for any **single-purpose** command in 8.0 and 8.1.

### 3.3. Method-Based Style (Symfony 8.1 NEW)

A single class hosts multiple commands. The constructor injects shared dependencies once. Each public method annotated with `#[AsCommand]` becomes an independent command.

```php
namespace App\Command;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class UserCommands
{
    public function __construct(
        private readonly UserRepository  $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsCommand('app:user:create', description: 'Creates a new user')]
    public function create(
        #[Argument] string $username,
        #[Option(shortcut: 'a')] bool $admin = false,
        OutputInterface $output,
    ): int {
        $this->logger->info(sprintf('Creating user "%s".', $username));
        // ...

        return Command::SUCCESS;
    }

    #[AsCommand('app:user:delete', description: 'Deletes an existing user')]
    public function delete(
        #[Argument] string $username,
        OutputInterface $output,
    ): int {
        $this->logger->info(sprintf('Deleting user "%s".', $username));
        // ...

        return Command::SUCCESS;
    }

    #[AsCommand('app:user:promote', description: 'Promotes a user to admin')]
    public function promote(
        #[Argument] string $username,
        OutputInterface $output,
    ): int {
        // ...

        return Command::SUCCESS;
    }
}
```

After this class is registered (autoconfigured automatically when services scan `src/Command/`), `bin/console list` reports three independent commands: `app:user:create`, `app:user:delete`, `app:user:promote`.

## 4. Side-by-Side - Symfony 8.0 vs 8.1 (Same Use Case)

**Goal:** group three related commands sharing the same `UserRepository` and `LoggerInterface`.

| Aspect | Symfony 8.0 | Symfony 8.1 |
|--------|-------------|-------------|
| Number of classes | 3 | 1 |
| Number of constructors | 3 (each duplicating DI) | 1 |
| `#[AsCommand]` placement | On each class | On each method |
| Class extends `Command`? | Optional (legacy) / no (invokable) | No |
| Discovery | Per-class autowire | Per-method autowire (new) |
| Refactoring cost when adding `app:user:lock` | New file + DI duplication | New method on existing class |

## 5. Argument and Option Attribute Reference

Both styles 3.2 and 3.3 use the same `#[Argument]` / `#[Option]` parameter-level attributes.

```php
public function __invoke(
    // Required argument: no default value
    #[Argument(description: 'Email address')]
    string $email,

    // Optional argument: has default value
    #[Argument]
    string $name = '',

    // Variadic argument: array with default
    #[Argument(description: 'Recipients')]
    array $recipients = [],

    // Boolean flag (--admin, no value)
    #[Option(shortcut: 'a')]
    bool $admin = false,

    // Option with value (--iterations=5)
    #[Option(description: 'How many times', shortcut: 'i')]
    int $iterations = 1,

    // Negatable flag (--debug or --no-debug)
    #[Option]
    bool $debug = true,

    // Nullable bool (--verbose, --no-verbose, or absent)
    #[Option]
    ?bool $verbose = null,

    // Array option (repeatable: --role=ADMIN --role=USER)
    #[Option(description: 'User roles')]
    array $roles = [],

    // Optional value (--output, --output=file.txt)
    #[Option]
    string|bool $output = false,

    OutputInterface $output_,
): int {
    // ...
}
```

**Mapping rules:**
- Argument vs Option attribute determines kind.
- Presence of a default value determines REQUIRED vs OPTIONAL.
- `bool` parameter → flag (no value); `bool` with `true` default → negatable (`--no-*` accepted); `?bool` → nullable flag.
- `array` parameter on `#[Option]` → repeatable option; on `#[Argument]` → variadic last argument.
- Union `string|bool` → option with optional value.
- Method parameter name (camelCase) is converted to the CLI flag name (kebab-case).

## 6. Console Argument Resolvers (Symfony 8.1 NEW)

Reference: <https://symfony.com/blog/new-in-symfony-8-1-console-argument-resolvers>

Symfony 8.1 introduces **value resolvers** for the console - the same pattern long used for controller arguments in the HTTP layer, now applied to `__invoke()` and method-based commands. Resolvers eliminate the manual transformation of raw CLI strings into entities, dates, enums, UIDs, and services.

### 6.1. The Problem - Before Symfony 8.1

```php
#[AsCommand(name: 'app:report:generate')]
final class GenerateReportCommand
{
    public function __construct(
        private UserRepository  $userRepository,
        private ReportGenerator $reportGenerator,
    ) {
    }

    public function __invoke(
        #[Argument] int    $userId,
        #[Option]   string $dateYmd,
    ): int {
        $user = $this->userRepository->find($userId);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd);
        // ...
        return Command::SUCCESS;
    }
}
```

Every command repeats the same boilerplate: receive a string/int, look up the entity, parse the date, validate the result. The repository is injected just for that one `find()` call.

### 6.2. The Solution - Resolvers Do the Conversion

```php
use App\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapDateTime;
use Symfony\Component\Console\Attribute\Option;

#[AsCommand(name: 'app:report:generate')]
final class GenerateReportCommand
{
    public function __construct(
        private ReportGenerator $reports,
    ) {
    }

    public function __invoke(
        #[Argument, MapEntity]
        User $user,

        // Alternative: resolve by a non-primary-key field
        // #[Argument, MapEntity(mapping: ['user' => 'email'])]
        // User $user,

        #[Option, MapDateTime(format: 'Y-m-d')]
        \DateTimeInterface $date,
    ): int {
        // ...
    }
}
```

The repository disappears from the constructor. The framework reads `userId` from the input, queries Doctrine, and passes the hydrated `User` directly. Same for the date.

### 6.3. Built-in Resolvers

| Resolver | Resolves | Triggered by |
|----------|----------|--------------|
| `BuiltinTypeValueResolver` | `string`, `int`, `float`, `bool`, `array` | `#[Argument]` / `#[Option]` on a parameter with a builtin type |
| `BackedEnumValueResolver` | Backed enum cases | `#[Argument]` / `#[Option]` on a `BackedEnum` parameter |
| `DateTimeValueResolver` | `DateTimeInterface` | `#[MapDateTime(format: '...')]` |
| `EntityValueResolver` | Doctrine entities | `#[MapEntity]` (`mapping`, `expr`, `id` options) |
| `UidValueResolver` | `Uuid`, `Ulid`, etc. | UID parameter type |
| `ServiceValueResolver` | Container services | Plain typed parameter without `#[Argument]` / `#[Option]` |
| `DefaultValueResolver` | Default value | Parameter has `=` default and input is absent |
| `VariadicValueResolver` | Variadic input | `...$param` parameter |
| `InputFileValueResolver` | `InputFile` (pasted image / file path) | `InputFile` parameter (see `symfony-console-io.md` §1.3) |
| `MapInputValueResolver` | Hydrated + validated input object | `#[MapInput]` (see `symfony-console-io.md` §1.5) |

All console-component resolvers live under `Symfony\Component\Console\ArgumentResolver\ValueResolver\` (e.g. `Symfony\Component\Console\ArgumentResolver\ValueResolver\ServiceValueResolver`). The lone exception is `EntityValueResolver`, which ships with the Doctrine bridge as `Symfony\Bridge\Doctrine\Console\ArgumentResolver\EntityValueResolver`.

### 6.4. Service Injection in `__invoke()` Parameters (NEW)

In 8.1, services may be injected as **method parameters** (not only constructor parameters). The `ServiceValueResolver` resolves any plain typed parameter without `#[Argument]` / `#[Option]` from the container:

```php
// Before - service via constructor:
public function __construct(
    private ReportGenerator $reports,
) {
}

public function __invoke(
    #[Argument] int $userId,
): int { /* ... */ }

// After - service inline as a method parameter:
public function __invoke(
    ReportGenerator $reports,
    #[Argument] int $userId,
): int { /* ... */ }
```

**When to use which:** keep the constructor for dependencies used by **multiple methods** of a method-based command class (the dependency is still shared). Inject directly in the method only when the dependency is local to one command and the class would otherwise carry an unused constructor field.

### 6.5. Advanced DI Attributes - `#[Autowire]`, `#[Target]`

The same DI-targeting attributes that work in controllers also work in console parameters:

```php
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:audit')]
final class AuditCommand
{
    public function __invoke(
        // Pick a specific service when multiple implement the same interface
        #[Autowire(service: 'messenger.bus.async')] MessageBusInterface $bus,

        // Use the Target attribute to select a non-default tagged service
        #[Target('security')] LoggerInterface $logger,

        // Container parameters are also available
        #[Autowire('%kernel.environment%')] string $env,
    ): int {
        // ...
        return Command::SUCCESS;
    }
}
```

### 6.6. Writing a Custom Resolver

Resolvers implement `Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface`. The `resolve()` method receives the argument name, the `InputInterface`, and a `Symfony\Component\Console\Attribute\Reflection\ReflectionMember` instance, and **must return an iterable** (empty array if it cannot resolve, otherwise an array of resolved values - even for a single value, because variadic arguments resolve to multiple values).

`ReflectionMember` describes the target parameter or property; the methods used most often are `getType(): ?\ReflectionType`, `getName(): string`, `hasDefaultValue(): bool`, and `getDefaultValue(): mixed`. Note `getType()` returns a `\ReflectionType`, NOT a class-string - resolve the class name from it before any class-based check.

```php
namespace App\Console\ValueResolver;

use App\IdentifierInterface;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

class BookingIdValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $type = $member->getType();
        $class = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        if (null === $class || !is_subclass_of($class, IdentifierInterface::class, true)) {
            return [];
        }

        $value = $input->getArgument($argumentName);
        if (!is_string($value)) {
            return [];
        }

        return [$class::fromString($value)];
    }
}
```

Returning `[]` tells the framework to defer to the next resolver in the priority chain; returning a non-empty array stops the chain.

### 6.7. Registering a Resolver

Two registration modes - **global** (applied to every parameter automatically) vs **targeted** (only when explicitly requested via `#[ValueResolver]`).

**Global resolver** - tag `console.argument_value_resolver` with optional `priority`. Higher priority runs first.

```php
namespace App\Console\ValueResolver;

use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'booking_id', priority: 150)]
class BookingIdValueResolver implements ValueResolverInterface
{
    // ...
}
```

YAML equivalent:

```yaml
# config/services.yaml
services:
    App\Console\ValueResolver\BookingIdValueResolver:
        tags:
            - console.argument_value_resolver:
                name: booking_id
                priority: 150
```

**Targeted resolver** - tag `console.targeted_value_resolver`. Skipped unless a parameter explicitly opts in with `#[ValueResolver]`. Use this when the resolver should NOT run for every parameter that happens to match its return type.

```php
use Symfony\Component\Console\Attribute\AsTargetedValueResolver;

#[AsTargetedValueResolver('booking_id')]
class BookingIdValueResolver implements ValueResolverInterface
{
    // ...
}
```

### 6.8. `#[ValueResolver]` - Forcing or Disabling a Resolver

```php
use App\Console\ValueResolver\BookingIdValueResolver;
use App\Reservation\BookingId;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ServiceValueResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\ValueResolver;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:booking:show')]
class ShowBookingCommand
{
    public function __invoke(
        // Force this specific resolver (matches the targeted tag name OR the resolver class FQCN)
        #[ValueResolver('booking_id')]
        BookingId $id,

        // OR pin to a class FQCN
        #[ValueResolver(BookingIdValueResolver::class)]
        BookingId $altId,

        // Disable a specific built-in resolver for this parameter
        #[ValueResolver(ServiceValueResolver::class, disabled: true)]
        MyService $service,
    ): int {
        // ...
    }
}
```

### 6.9. Inspecting Registered Resolvers

```bash
/usr/bin/php /srv/${DKZ_DOMAIN}/bin/console debug:container console.argument_resolver
```

Lists all services tagged `console.argument_value_resolver` with their priority, allowing diagnosis of resolution-order issues.

### 6.10. Resolver Cheatsheet

| Need | Recipe |
|------|--------|
| Receive a Doctrine entity by primary key | `#[Argument, MapEntity] EntityClass $entity` |
| Receive a Doctrine entity by another field | `#[Argument, MapEntity(mapping: ['arg_name' => 'field_name'])] EntityClass $entity` |
| Parse a date | `#[Option, MapDateTime(format: 'Y-m-d')] \DateTimeInterface $date` |
| Receive a backed enum | `#[Argument] StatusEnum $status` (no extra attribute) |
| Inject a service inline | `ServiceClass $service` (no `#[Argument]`/`#[Option]`) |
| Pick a specific service | `#[Autowire(service: 'messenger.bus.async')] MessageBusInterface $bus` |
| Tagged service variant | `#[Target('security')] LoggerInterface $logger` |
| Container parameter | `#[Autowire('%param.name%')] string $param` |
| Custom value object | Custom `ValueResolverInterface` + `#[ValueResolver('name')]` on the parameter |
| Disable a built-in resolver | `#[ValueResolver(BuiltInResolver::class, disabled: true)]` |

## 7. Choosing a Style

| Need | Use |
|------|-----|
| Single command, no lifecycle hooks | Style 3.2 (`__invoke`) |
| Single command, needs `initialize()` / `interact()` | Style 3.1 (legacy `execute()`) |
| 2+ closely related commands sharing the same dependencies | Style 3.3 (method-based, **Symfony 8.1+ only**) |
| Existing legacy commands | Leave as-is unless touched for other reasons |

Do **not** mix styles in one class. A method-based class must contain only `#[AsCommand]`-annotated methods (plus the constructor and private helpers). It must NOT extend `Command`.

## 8. The Stub's Existing Command Code on 8.1

The stub already ships commands in two styles. This section is the policy for what to do with each on 8.1 and which existing scaffolding to reuse for new commands.

### 8.1. Keep the Legacy `CommandMiddleware` + `configure()` Commands - Do NOT Port Them

The stub mixes two styles intentionally:

| Style | Where in the stub | Use for |
|---|---|---|
| **Legacy `CommandMiddleware` + `configure()`** | `src/Command/SimpleCommand.php`, `src/Command/Dev/DevCommand.php`, `src/Command/Entity/*` | Commands that need the middleware features the stub already implements: `try()` dispatcher, `dryRun`, `databaseReset`, `databaseDrop`, `databaseMigrate`, `auditDropAndRecreateSchema`, `hasTty()`. |
| **Invokable / method-based** | `src/Command/Dev/InvokableExampleCommand.php` (§8.3) and new commands | NEW commands with simple I/O - arguments, options, optional interactive fill-in, optional cross-field DTO mapping. |

**Do NOT port the legacy commands to the invokable or method-based style "for consistency".** They earn their inheritance: `CommandMiddleware` is project-specific scaffolding, not Symfony framework code, and the invokable styles cannot host the `try()` dispatcher / DB-lifecycle helpers those commands rely on. Mixed styles are fine and supported (the legacy `execute()` style is NOT deprecated - see §1).

### 8.2. Action-Dispatch Pattern (Pre-8.1 Workaround)

The v8.0 stub also uses an in-house pattern in `DemoCommand` where a single command exposes multiple actions via `--action=name`, dispatching internally to private methods. This was the pre-8.1 way to avoid creating one class per related operation.

**In Symfony 8.1, prefer method-based commands** (§3.3) over the action-dispatch pattern for new code:
- Each operation gets its own `bin/console` entry (visible in `list`, autocompletable).
- No string-to-method dispatch logic to maintain.
- Tab completion and `--help` work per command.

Keep the action-dispatch pattern only when the operations truly are sub-modes of a single development command (e.g., `app:dev:demo` exploratory tooling), not stable user-facing commands.

### 8.3. Reference Scaffolding - `InvokableExampleCommand`

The invokable / method-based features in this rule have a worked reference at `src/Command/Dev/InvokableExampleCommand.php`. Read it before writing a new simple command: it demonstrates the `#[Argument]` / `#[Option]` attributes (§5), `#[Ask]` interactive fill-in (`symfony-console-io.md` §1.1), backed-enum arguments (`symfony-console-io.md` §1.2), and `#[MapInput]` DTO mapping (`symfony-console-io.md` §1.5) on a real command. New simple commands should follow it rather than re-deriving the attribute wiring from scratch.

### 8.4. `#[Interact]` - Method-Level Cross-Field Prompting

`Symfony\Component\Console\Attribute\Interact` (a `#[\Attribute(\Attribute::TARGET_METHOD)]`) marks a method on an invokable / method-based command class. The framework runs that method after CLI parsing but BEFORE `__invoke()` (the invokable analogue of the legacy `interact()` lifecycle hook). Use it ONLY for interactive logic that spans multiple inputs - one missing value whose prompt depends on another already-resolved value.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:deploy')]
final class DeployCommand
{
    #[Interact]
    public function interact(SymfonyStyle $io, InputInterface $input): void
    {
        // Cross-field logic: only prompt for --window when --rolling was passed.
        if ($input->getOption('rolling') && null === $input->getOption('window')) {
            $input->setOption('window', (int) $io->ask('Rolling-restart window (seconds)', '30'));
        }
    }

    public function __invoke(
        #[Option] bool $rolling = false,
        #[Option] ?int $window = null,
    ): int {
        // ...
        return Command::SUCCESS;
    }
}
```

For "fill this ONE field if missing", use `#[Ask]` on the parameter instead (`symfony-console-io.md` §1.1) - it is localised to the parameter and easier to read. Reach for `#[Interact]` only when the prompt for one input depends on the value of another.

## 9. Testing Commands

Two testing surfaces are available in 8.1. Both are valid; pick based on where the command runs.

### 9.1. Result-based testing - `runCommand()` (Symfony 8.1 NEW, preferred)

Symfony 8.1 adds a `runCommand()` test helper that runs a command and returns an immutable `Symfony\Component\Console\Tester\ExecutionResult` instead of mutating a tester object. It replaces `executeCommand()` in command tests (the older `CommandTester::execute()` remains for the standalone Console component). On a `KernelTestCase`, call it statically:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Framework\Attributes\Group;

final class CreateUserCommandTest extends KernelTestCase
{
    #[Group(GroupAttributeValidationTest::GROUP_INTEGRATION)]
    public function testCreate(): void
    {
        // Signature: runCommand($name, array $inputArgs = [], array $interactiveInputs = [])
        // The 3rd argument feeds answers to interactive prompts (see `symfony-console-io.md` §1.1), e.g. ['alice', 'yes'].
        $result = static::runCommand('app:user:create', ['username' => 'alice', '--admin' => true]);

        $this->assertCommandIsSuccessful($result);
        $this->assertStringContainsString('Created alice', $result->getDisplay());
    }
}
```

`ExecutionResult` is read-only:

| Member | Returns | Notes |
|--------|---------|-------|
| `$result->statusCode` | `int` | The exit code (public readonly property) |
| `$result->input` | `string` | The reconstructed input line (public readonly property) |
| `$result->getDisplay(bool $normalize = true)` | `string` | Combined stdout + stderr |
| `$result->getOutput(bool $normalize = false)` | `string` | stdout only |
| `$result->getErrorOutput(bool $normalize = false)` | `string` | stderr only |
| `$result->dump()` / `$result->dd()` | `static` / `never` | Debug the result inline |

New 8.1 result-based assertions (each takes the `ExecutionResult` as the first argument):

| Assertion | Passes when |
|-----------|-------------|
| `$this->assertCommandIsSuccessful($result)` | exit code `0` (`Command::SUCCESS`) |
| `$this->assertCommandFailed($result)` | exit code `1` (`Command::FAILURE`) |
| `$this->assertCommandIsInvalid($result)` | exit code `2` (`Command::INVALID`) |
| `$this->assertCommandResultEquals($result, expectedStatusCode: 0, expectedOutput: '...')` | the supplied expectations all match |

### 9.2. `CommandTester` and method-based commands (standalone Console)

When testing without the kernel (the standalone Console component), `CommandTester` accepts the first-class callable syntax for a single method-based command:

```php
use Symfony\Component\Console\Tester\CommandTester;

$tester = new CommandTester((new UserCommands($users, $logger))->create(...));
$tester->execute(['username' => 'alice', '--admin' => true]);
$tester->assertCommandIsSuccessful();
```

The `->method(...)` first-class callable is the supported way to obtain a callable referring to a single method-based command. The tester-bound `assertCommandIsSuccessful()` / `assertCommandFailed()` / `assertCommandIsInvalid()` helpers (on `TesterTrait`) take NO result argument here - they read the tester's own captured state; the result-based forms in §9.1 are the ones that take an `ExecutionResult`.
