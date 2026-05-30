# Symfony Console Input and Output

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-30 | 2026-05-30 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-improved-console-input
* https://symfony.com/blog/new-in-symfony-7-3-new-and-improved-console-helpers
* https://symfony.com/doc/8.1/console.html
* https://symfony.com/doc/8.1/components/console/helpers/table.html
* https://symfony.com/doc/8.1/components/console/helpers/tree.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Style/SymfonyStyle.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Helper/TreeHelper.php
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Console/Helper/TreeNode.php

Companion to `symfony-command.md` (which covers command DEFINITION, argument resolvers, and testing). This rule documents how console commands talk to the user: the Symfony 8.1 improved console INPUT features (section 1: interactive prompts, file/image input, answer validation, mapped-object validation, richer option defaults, original-input forwarding), the smaller console additions (section 2: `SymfonyStyle` outline blocks + progress-bar customization, OSC 9;4 terminal progress, optional PSR container on `Application`), and the console OUTPUT helpers (section 3: the `markdown` table style and the `TreeHelper` / `TreeNode` tree renderer). Everything here is additive and applies to the invokable / method-based command styles defined in `symfony-command.md`. The two files were split to stay under the rule-file size limit; new console material belongs in one of them (never a new per-feature console file).

## 1. Improved Console Input (Symfony 8.1 NEW)

Reference: <https://symfony.com/blog/new-in-symfony-8-1-improved-console-input>

Symfony 8.1 adds the following additive improvements to how invokable and method-based commands (see `symfony-command.md` §3.2-§3.3) receive input. They build on the same `#[Argument]` / `#[Option]` parameter model from `symfony-command.md` §5. Nothing here is required - existing commands keep working - and the validation features (§1.4, §1.5) activate only when `symfony/validator` is installed (the Dockraft stub ships it via API Platform).

| # | Feature | New API | Requires |
|---|---------|---------|----------|
| 1 | Prompt interactively for a missing value | `#[Ask]` | Console |
| 2 | Interactive choice (single / multi / enum) | `#[AskChoice]` | Console |
| 3 | Paste an image or type a file path | `InputFile` + `#[Ask]` | Console + supported terminal |
| 4 | Validate an interactive answer | `#[Ask(constraints: [...])]`, `Question::setConstraints()` | Console + Validator |
| 5 | Validate a mapped input object | `#[MapInput]` + `#[Assert\*]` | Console + Validator |
| 6 | Boolean default on a negatable option | `InputOption::VALUE_NEGATABLE` + bool default | Console |
| 7 | Object default value for an argument/option | object literal as parameter default | Console |
| 8 | Read / forward the original raw input | `RawInputInterface` | Console (+ Process to forward) |

### 1.1. Interactive Prompts - `#[Ask]`

`Symfony\Component\Console\Attribute\Ask` makes the framework prompt the user when the value is not supplied on the command line. Combine it with `#[Argument]` / `#[Option]`:

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:user:create')]
final class CreateUserCommand
{
    public function __invoke(
        #[Argument, Ask('What is the username?')]
        string $username,
    ): int {
        // Run with no argument -> the user is prompted "What is the username?".
        // Run as `app:user:create alice` -> no prompt, $username = 'alice'.
    }
}
```

The prompt only fires in interactive mode. Under `--no-interaction` (or no TTY) the framework falls back to the parameter default; a required argument with no default errors as usual.

### 1.2. Choice Prompts - `#[AskChoice]`

`Symfony\Component\Console\Attribute\AskChoice` declares a single- or multi-selection from a fixed list. The resolved value is constrained to the listed choices.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AskChoice;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('app:create-user')]
class CreateUserCommand
{
    public function __invoke(
        #[Argument, AskChoice('Select a role', ['admin', 'editor', 'viewer'])]
        string $role,
    ): int {
        // $role is one of: 'admin', 'editor', 'viewer'
    }
}
```

Multiple selections - type the parameter as `array`:

```php
#[Argument, AskChoice('Select roles', ['admin', 'editor', 'viewer'])]
array $roles,
```

Backed enum - the choices are auto-derived from the enum cases, so the list argument is omitted:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

public function __invoke(
    #[Argument, AskChoice('Select a status')]
    Status $status, // choices: 'active', 'inactive'
): int {
    // ...
}
```

### 1.3. File and Image Input - `InputFile`

Type a parameter as `Symfony\Component\Console\Input\File\InputFile` together with `#[Ask]` and the question helper switches to file-input mode: on supported terminals the user can paste an image directly; elsewhere it falls back to accepting a file path.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\File\InputFile;

#[AsCommand('app:analyze')]
class AnalyzeCommand
{
    public function __invoke(
        #[Argument, Ask('Provide an image (paste it or enter a path):')]
        InputFile $image,
    ): int {
        // $image comes from a pasted image or a file path
    }
}
```

Terminals that support image paste: Ghostty, iTerm2, Kitty, WezTerm, Konsole, Warp, and more. Other terminals accept a file path.

### 1.4. Validating Interactive Answers

`#[Ask]` accepts a `constraints` argument - a list of `#[Assert\*]` constraints applied to the answer. On failure the user is re-prompted until the answer is valid.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Validator\Constraints as Assert;

public function __invoke(
    #[Argument, Ask('Enter your email:', constraints: [
        new Assert\NotBlank(), new Assert\Email()
    ])]
    string $email,
): int {
    // $email is guaranteed to be a non-empty, valid email
}
```

Imperative equivalent on the `Question` helper via the new `Question::setConstraints()`:

```php
$question = new Question('Enter a URL:');
$question->setConstraints([new Assert\Url()]);

$url = $io->askQuestion($question);
```

Validation runs only when `symfony/validator` is installed; otherwise the constraints are ignored. Use the same named-argument constraint style required by `validator.md` §1.

### 1.5. Validating Mapped Input Objects - `#[MapInput]`

`Symfony\Component\Console\Attribute\MapInput` hydrates a plain object from the command's arguments and options (each property carries its own `#[Argument]` / `#[Option]`). In 8.1 the mapped object is auto-validated against its `#[Assert\*]` constraints.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserInput
{
    #[Argument]
    #[Assert\NotBlank]
    public string $name;

    #[Option]
    #[Assert\Email]
    public ?string $email = null;
}

#[AsCommand('app:create-user')]
class CreateUserCommand
{
    public function __invoke(#[MapInput] CreateUserInput $input): int
    {
        // $input is already validated
    }
}
```

Validation groups:

```php
#[MapInput(validationGroups: ['registration'])]
CreateUserInput $input
```

On failure, `InputValidationFailedException` is thrown carrying the list of violations. As with §1.4, validation is skipped when the Validator component is absent. `#[MapInput]` is the console analogue of HTTP `#[MapRequestPayload]` (see `request-payload-mapping.md`) - a DTO mapped + validated from the request, here from the CLI input.

### 1.6. Boolean Defaults for Negatable Options

In the imperative builder API (legacy `execute()` style, `symfony-command.md` §3.1), `InputOption::VALUE_NEGATABLE` now accepts a boolean default value. A negatable option accepts both the flag (`--yell`) and its negation (`--no-yell`).

```php
$this
    // ...
    ->addOption('yell', null, InputOption::VALUE_NEGATABLE, 'Whether to yell', false)
;
```

The attribute-style equivalent - a `bool` parameter with a `true` default making `--no-*` available - is in `symfony-command.md` §5 ("`bool` with `true` default -> negatable").

### 1.7. Object Default Values for Arguments and Options

Invokable / method-based commands can now use object instances as default values for input parameters. Previously option defaults could not be objects.

```php
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;

#[AsCommand('app:report')]
class ReportCommand
{
    public function __invoke(
        #[Argument] string $name,
        #[Option] \DateTimeImmutable $from = new \DateTimeImmutable(),
    ): int {
        // $from defaults to "now" when --from is not passed
    }
}
```

Relation to the `symfony-command.md` §6 resolvers: use a `#[MapDateTime]` / `#[MapEntity]` resolver when the value is parsed FROM the input string; use an object default (this section) when the value is a sensible fallback computed when the input is ABSENT.

### 1.8. Forwarding the Original Input - `RawInputInterface`

A command can receive `Symfony\Component\Console\Input\RawInputInterface` to access only the input the user explicitly passed (without applied defaults) and re-serialize it - useful for spawning a sub-process that mirrors the current invocation minus a few options.

New methods:
- `getRawArguments()` - only explicitly passed arguments (no defaults)
- `getRawOptions()` - only explicitly passed options (no defaults)
- `unparse()` - turn parsed options back into command-line form

```php
use Symfony\Component\Console\Input\RawInputInterface;
use Symfony\Component\Process\Process;

// inside a command that receives RawInputInterface $input
$options = $input->getRawOptions();
unset($options['main-process-only-option']);

$process = new Process([
    \PHP_BINARY, 'bin/console', 'my:command',
    ...$input->getRawArguments(),
    ...$input->unparse(array_keys($options)),
]);
```

### 1.9. Anti-patterns

- **`#[Ask]` on a command that runs non-interactively (cron, CI, supervisord)** - with `--no-interaction` (or no TTY) there is nobody to answer the prompt. Always provide a sensible default OR pass the value on the command line for automated invocations. The stub's `cron.tab` jobs and the `messenger:consume` worker (see `messenger.md` §17) run non-interactively.
- **Expecting `#[Ask(constraints: ...)]` / `#[MapInput]` to enforce validation without `symfony/validator`** - constraints are silently ignored when the component is absent. It is present in this stub; document the dependency for commands you intend to be portable.
- **Catching `InputValidationFailedException` only to re-throw a generic message** - it already carries the structured violation list; surface those messages instead of flattening them.
- **Relying on `InputFile` paste mode in a piped / non-TTY context** - image paste needs an interactive terminal; in pipes it can only accept a path.
- **Hand-rolling "what did the user actually pass" by diffing against defaults** - that is exactly what `RawInputInterface::getRawArguments()` / `getRawOptions()` provide. Use them.

### 1.10. Migration Notes (8.0 to 8.1)

No migration required - every feature in section 1 is additive and opt-in. Adopt per command as needed:

1. Replace manual `QuestionHelper` prompting in `interact()` with `#[Ask]` / `#[AskChoice]` on invokable commands.
2. Replace post-prompt manual validation loops with `#[Ask(constraints: ...)]` or `Question::setConstraints()`.
3. Replace hand-written input-DTO validation with `#[MapInput]` + `#[Assert\*]`.
4. Replace "spawn a sub-process mirroring this call" boilerplate with `RawInputInterface`.

## 2. Other Console Additions in Symfony 8.1

Smaller console-component additions in 8.1, all additive. They are not tied to the input features in section 1, but are part of the console I/O surface.

### 2.1. `SymfonyStyle` Output - Outline Blocks and Progress-Bar Customization

`SymfonyStyle` gains an outline-style variant of its admonition blocks (the framed `success` / `error` / etc. callouts), drawn with an outline rather than a solid background:

```php
use Symfony\Component\Console\Style\SymfonyStyle;

$io = new SymfonyStyle($input, $output);

$io->outlineSuccess('Deployment finished.');
$io->outlineError('Something went wrong.');
$io->outlineWarning('Low disk space.');
$io->outlineNote('Cache was warmed.');
$io->outlineInfo('3 records imported.');
$io->outlineCaution('This action is irreversible.');

// Generic form (custom type + style), mirroring block():
$io->outlineBlock('Custom message', type: 'APP', style: 'fg=cyan');
```

The progress-bar shortcuts now accept an optional output `format` so the bar's template can be set at creation without configuring the underlying `ProgressBar` separately:

```php
$io->progressStart(100, format: 'debug');
// ... $io->progressAdvance() in a loop ...
$io->progressFinish();

$bar = $io->createProgressBar(100, format: 'very_verbose');

foreach ($io->progressIterate($items, format: 'debug') as $item) {
    // ...
}
```

The pre-8.1 zero-/one-argument forms (`progressStart(100)`, `createProgressBar(100)`, `progressIterate($items)`) keep working unchanged.

### 2.2. OSC 9;4 Terminal Progress Reporting

When a `ProgressBar` runs, 8.1 also emits the OSC 9;4 escape sequence, which terminals that support it (and OS taskbars / docks) use to show a native progress indicator outside the text output - e.g. a progress overlay on the terminal's taskbar icon. This is automatic: the existing `ProgressBar` / `SymfonyStyle` progress API drives it; there is no method to call. Terminals that do not understand the sequence ignore it, so it is safe in pipes, CI logs, and non-supporting emulators.

### 2.3. Optional PSR Container on `Application`

`Symfony\Component\Console\Application` accepts an optional `Psr\Container\ContainerInterface` as a constructor argument. This lets a standalone console application (one not built on FrameworkBundle) resolve command-related services from any PSR-11 container, without depending on the Symfony DI container specifically:

```php
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;

/** @var ContainerInterface $container */
$application = new Application(container: $container);
```

Inside Symfony (the Dockraft stub), the framework already wires the kernel container into the `Application`, so this matters only for hand-built console binaries that want PSR-11 service resolution. Combine it with the per-method command registration from `symfony-command.md` §3.3 - `$application->addCommand($instance->create(...))` - when assembling such a binary by hand.

## 3. Console Output Helpers

Section 1 covers console INPUT and section 2 the other additions; `symfony-command.md` covers command definition, argument resolvers, and testing. This section covers OUTPUT helpers - the `markdown` table style and the `TreeHelper` / `TreeNode` tree renderer. Both shipped on the 7.3 line and are present and unchanged on 8.1 (verified against the 8.1 console-helpers docs). They apply to every command style (legacy `execute()`, invokable, method-based).

### 3.1. Markdown Tables

For commands whose output is pasted into GitHub / GitLab issues or documentation, render a real Markdown pipe table with `setStyle('markdown')`:

```php
use Symfony\Component\Console\Helper\Table;

(new Table($output))
    ->setHeaders(['Version', 'Release Date', 'Is LTS?'])
    ->setRows([
        ['Symfony 8.0', 'November 2025', 'No'],
        ['Symfony 8.1', 'November 2025', 'No'],
    ])
    ->setStyle('markdown')
    ->render();
```

Output is a ready-to-paste Markdown pipe table:

```
| Version     | Release Date  | Is LTS? |
|-------------|---------------|---------|
| Symfony 8.0 | November 2025 | No      |
| Symfony 8.1 | November 2025 | No      |
```

Use it for `app:report:*` commands whose result is meant to be archived in a PR description, a changelog, or a runbook. The other predefined styles are `default`, `compact`, `borderless`, `box`, and `box-double`.

### 3.2. Tree Helper

For commands that visualise hierarchical data (file system, organisation chart, dependency tree, workflow places), `Symfony\Component\Console\Helper\TreeHelper` renders a tree from a `SymfonyStyle` instance plus a nested array:

```php
use Symfony\Component\Console\Helper\TreeHelper;

$tree = TreeHelper::createTree($io, null, [
    'src' => [
        'Command',
        'Controller' => ['DefaultController.php'],
        'Kernel.php',
    ],
    'templates' => ['base.html.twig'],
]);

$tree->render();
```

Output:

```
├── src
│   ├── Command
│   ├── Controller
│   │   └── DefaultController.php
│   └── Kernel.php
└── templates
    └── base.html.twig
```

In the nested-array form, a string key is a branch label and its array value holds the children; a bare string value (numeric key) is a leaf.

### 3.3. Programmatic Build - `TreeNode`

When the hierarchy is generated at runtime, build the tree node-by-node with `Symfony\Component\Console\Helper\TreeNode` instead of preparing a nested array. `TreeNode::fromValues()` seeds a node from an array; `addChild()` accepts a string OR another `TreeNode`:

```php
use Symfony\Component\Console\Helper\TreeHelper;
use Symfony\Component\Console\Helper\TreeNode;

$root = new TreeNode('my-project/');
$root->addChild('src/');

$tests = new TreeNode('tests/');
$tests->addChild(new TreeNode('Functional/'));
$root->addChild($tests);

TreeHelper::createTree($io, $root)->render();
```

### 3.4. Built-in Tree Styles - `TreeStyle`

A tree style is a `Symfony\Component\Console\Helper\TreeStyle` object passed as the FOURTH argument of `createTree($io, $node, $nestedArray, $style)`. It is NOT a plain string - there is no `setStyle('box')`-style call on the tree (unlike the `Table` helper in §3.1). Use the factory methods:

| Factory method | Use for |
|---|---|
| `TreeStyle::default()` | The default look when no style is passed. |
| `TreeStyle::minimal()` | Plain-text logs. |
| `TreeStyle::compact()` | Dense output. |
| `TreeStyle::light()` | Light box-drawing characters. |
| `TreeStyle::box()` | Single-line box characters. |
| `TreeStyle::doubleBox()` | Emphatic double-line separation (the 7.x `box-double` style). |
| `TreeStyle::rounded()` | Rounded corners for human-facing reports. |

```php
use Symfony\Component\Console\Helper\TreeHelper;
use Symfony\Component\Console\Helper\TreeStyle;

$tree = TreeHelper::createTree($io, $node, [], TreeStyle::box());
$tree->render();

// Custom style - the TreeStyle constructor takes the drawing characters directly:
$custom = new TreeStyle('| ', '|-', '|_', '| ', '  ', '  ');
TreeHelper::createTree($io, $node, [], $custom)->render();
```

### 3.5. Output-Helper Rules

1. **Markdown tables are for ARCHIVABLE output.** A regular `Table` (default style) is fine for transient terminal output. Do NOT use the `markdown` style for default human-facing commands.
2. **Trees beat nested bullet lists** for any structure deeper than 2 levels. Bullet lists become unreadable; trees stay scannable.
3. **Use `SymfonyStyle::table(...)` for one-shot tables.** The fluent `new Table(...)` form is needed ONLY when you set a non-default style or per-cell behavior. For most commands, `$io->table($headers, $rows)` is the right call.
4. **Render once.** The `Table` / `TreeHelper` helpers buffer state - calling `->render()` twice produces duplicate output. Build, render, done.
5. **No ANSI in archived output.** Commands that produce markdown tables for issues / docs MUST run with `--no-ansi` (or detect via `isDecorated()`) - otherwise the escape codes leak into the pasted output.

### 3.6. Output-Helper Common Errors

| Error | Cause | Fix |
|---|---|---|
| Markdown table rendered with box-drawing characters | Forgot `->setStyle('markdown')` | Set the style explicitly before `->render()`. |
| Tree shows numeric keys (`0`, `1`, ...) instead of nested labels | Wrong nested-array shape | Use string keys for branch labels and arrays for children; numeric keys are leaves. |
| `TreeHelper::createTree(): Argument #4 must be of type TreeStyle, string given` | Passed a style name string (`'box'`) instead of a `TreeStyle` object | Pass `TreeStyle::box()` (or another factory) as the 4th argument; tree styles are NOT plain strings (§3.4). |
| Output cut at terminal width | `Table` honors the `COLUMNS` env var | Set `setColumnMaxWidth($idx, $width)` explicitly, OR run with `COLUMNS=200 bin/console ...`. |
