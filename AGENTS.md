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

- Aurora is a Symfony 7.4 bundle requiring PHP 8.4 or newer.
- Follow PSR-4 autoloading with the `Sindla\\Bundle\\AuroraBundle` namespace.
- Place application code in `src/` and tests in `tests/`.

## Installing

This is a Symfony bundle. To install the project as a Symfony bundle and run PHPUnit tests, follow these steps:

1. Clone the project into a directory (e.g., /workspace/Aurora)
2. Create a new empty directory (e.g., /workspace/Aurora-installed) then run the following commands:
   2.1 `mkdir /workspace/Aurora-installed` - if the directory does not exists
   2.2 `cd /workspace/Aurora-installed`
   2.3 `yes | composer create-project symfony/skeleton:7.4.x-dev . --no-cache`
   2.4 `yes | composer require symfony/webapp-pack -W --no-progress`
   2.5 `composer config repositories.aurora '{"type":"path","url":"/workspace/Aurora","options":{"symlink":true}}'`
   2.6 `yes | composer require sindla/aurora:7.4.x-dev -W --no-progress`
   2.7 `yes | composer require phpunit/phpunit:^12.3 -W --dev --no-progress`
   2.8 `yes | composer require dama/doctrine-test-bundle:^8.3 -W --dev --no-progress`
   2.9 `yes | composer require phpstan/phpstan:^2.1 -W --dev --no-progress`
   2.9 `cd /workspace/Aurora-installed/vendor/sindla/aurora/`
   3.10 `composer install`
   3.11 `cd /workspace/Aurora-installed`
   3.12 `php bin/console cache:clear --env=dev`
   3.13 Execute unit tests using command
   `KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpunit --no-coverage -c /workspace/Aurora-installed/vendor/sindla/aurora/phpunit.xml.dist /workspace/Aurora-installed/vendor/sindla/aurora/tests/`
   3.14 Run static analysis using command `KERNEL_CLASS=App\\Kernel APP_ENV=test php /workspace/Aurora-installed/vendor/bin/phpstan analyse -l 6 /workspace/Aurora-installed/vendor/sindla/aurora//src`

Code changes/bug fixes – change only the files located inside `/workspace/Aurora-installed/vendor/sindla/aurora/` – also add to git only files from this directory!

## Testing

- The `composer.lock` file must never be added to git.
- For coverage reports, replace `--no-coverage` with `--coverage-clover coverage.xml`.
- In PHPUnit tests using a data provider, include only the `#[DataProvider(...)]` attribute and NOT the `@dataProvider` annotation.
- When a PHP file (class, method, trait, etc.) is modified, verify and run all existing PHPUnit tests for that file before completing the task and finish only if these tests pass.

## Codex

If you are Codex, before running PHPUnit tests, execute the following commands:

```
composer create-project symfony/skeleton:7.4 temp_project --no-cache

mv temp_project/* temp_project/.* .

rm -rf temp_project

composer require symfony/webapp-pack
```

