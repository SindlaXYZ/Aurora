# AGENTS Instructions

## Code Style

- Use UTF-8 encoding with LF line endings.
- Indent using 4 spaces and trim trailing whitespace.
- Ensure each file ends with a newline and stays within 199 characters per line.
- Write all source code and comments exclusively in English.
- Ensure all git branch names, tags, commit messages, and related text are written exclusively in English. Example:
  - wrong: `codex/gaseste-si-repara-un-bug-in-cod-u6qdzr`
  - correct: `codex/find-and-fix-a-bug-in-the-code-u6qdzr`

## PHP Guidelines

- Aurora is a Symfony 7.3 bundle requiring PHP 8.4 or newer.
- Follow PSR-4 autoloading with the `Sindla\\Bundle\\AuroraBundle` namespace.
- Place application code in `src/` and tests in `tests/`.

## Testing

- Install dependencies with `composer install`. The `composer.lock` file must never be added to git.
- Run static analysis using `vendor/bin/phpstan analyse`.
- Execute unit tests using `vendor/bin/phpunit --no-coverage -c phpunit.xml.dist`.
  For coverage reports, replace `--no-coverage` with `--coverage-clover coverage.xml`.
- In PHPUnit tests using a data provider, include both the `#[DataProvider(...)]` attribute and the `@dataProvider` annotation.
- When a PHP file (class, method, trait, etc.) is modified, verify and run all existing PHPUnit tests for that file before completing the task and finish only if these tests pass.

## Codex

If you are Codex, before running PHPUnit tests, execute the following commands:

```
composer create-project symfony/skeleton:7.3 temp_project --no-cache

mv temp_project/* temp_project/.* .

rm -rf temp_project

composer require symfony/webapp-pack
```

