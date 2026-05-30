# Monolog - Per-Exception Logging and MailerHandler

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/logging.html
* https://symfony.com/doc/8.1/reference/configuration/framework.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Bridge/Monolog/Handler/MailerHandler.php

Covers the per-exception `log_channel:` mechanism (introduced in 7.3, unchanged on 8.1) that ties together `framework.exceptions` (where the routing decision lives) and `monolog.channels` (where the channel is declared), PLUS the one Symfony 8.1 addition: a `$subjectMaxLength` option on the MonologBridge `MailerHandler` (section 7).

The `framework.exceptions.<FQCN>.log_channel:` routing and the `monolog.channels` wiring have **no behavior change in `CHANGELOG-8.1.md`** - sections 1-6 are carried over from the 7.3 line. Section 7 is the only 8.1-specific addition.

## 1. Two Files, Two Roles

| File | Role |
|---|---|
| `config/packages/framework.yaml` | DECIDES which channel each exception class goes to. The `framework.exceptions.<FQCN>.log_channel:` key is the dispatcher. |
| `config/packages/monolog.yaml` | DECLARES the channels and binds them to handlers. The `monolog.channels: [...]` list registers them; `monolog.handlers.*.channels:` filters per handler. |

A channel referenced in `framework.exceptions` but missing from `monolog.channels` triggers a container-compile error: `The channel "routing" is not configured.`

## 2. Default Channels in This Stub

The stub registers three channels in `monolog.yaml.example`:

| Channel | What it carries |
|---|---|
| `deprecation` | Anything routed via `trigger_deprecation()` and the framework's deprecation reporter. |
| `routing` | `NotFoundHttpException`, `MethodNotAllowedHttpException`, `ResourceNotFoundException`. |
| `security` | `AuthenticationException`, `AccessDeniedException`. |

These mappings live in `framework.yaml.example` under `framework.exceptions:`. To add another exception class to a channel, append a new entry under `framework.exceptions:` with `log_channel: <channelName>`. To add another channel, also append it to `monolog.channels:`.

## 3. Rules

1. **Always pair the two files.** A `log_channel:` in `framework.yaml` without a matching entry in `monolog.channels:` is a compile-time error. A channel declared in `monolog.channels:` but unused in `framework.exceptions` is harmless but pointless - remove it.
2. **`log_level:` and `log_channel:` are independent.** `log_level: info` controls VERBOSITY (which level the exception gets recorded at). `log_channel: routing` controls DESTINATION. Set both deliberately - most "spam" complaints are about level, not channel.
3. **Pick channel names by concern, not by exception name.** `routing`, `security`, `payment`, `webhook` - generic concerns. NOT `not_found`, `unauthorized` - those name a single exception class and rot when the project grows.
4. **Route to a dedicated handler when you actually want a separate log file.** Just adding the channel doesn't split the log. Add a handler under each `when@*` block that filters on `channels: ['routing']` and writes to its own path / stream.
5. **In tests, override or silence.** The stub's `when@test` block already overrides exception log levels for API Platform `ValidationException` and `MethodNotAllowedHttpException`. Use the same pattern for any custom exception that floods test output.

## 4. Wiring a Dedicated Handler for a Channel

```yaml
when@prod:
    monolog:
        handlers:
            routing:
                type: stream
                channels: ['routing']
                path: '%kernel.logs_dir%/routing.log'
                level: info
                formatter: monolog.formatter.json
            security:
                type: stream
                channels: ['security']
                path: '%kernel.logs_dir%/security.log'
                level: warning
                formatter: monolog.formatter.json
```

Pre-existing `main` handlers stay - these two ADD dedicated streams. The `main` handler must continue excluding the new channels (`channels: ["!routing", "!security", "!deprecation"]`) to avoid double-logging.

## 5. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `The channel "..." is not configured.` at compile time | `framework.exceptions.<...>.log_channel:` references a channel not in `monolog.channels:` | Add the channel name to `monolog.channels:` in `monolog.yaml`. |
| All exceptions still landing in `main.log` | Channels declared but no dedicated handler filters on them | Add a handler with `channels: ['<name>']`; ensure `main` excludes them via `["!name"]`. |
| Test suite spams logs with HTTP 4xx | Default `error` level applied to `Method/Not Found` | Override `log_level: info` for those classes in `when@test.framework.exceptions`. |
| Exception class typo in `framework.exceptions` silently does nothing | FQCN does not match a real class | Use the full FQCN with `::class` syntax when possible, OR verify the string via `composer dump-autoload --classmap-authoritative` + grep. |

## 6. `ErrorListener::logException()` Channel Argument (carried from 8.0)

When extending or replacing the kernel's `ErrorListener`, note its `logException()` method takes a `$logChannel` argument (added in the 8.0 line). This is the seam the `framework.exceptions.<FQCN>.log_channel:` routing flows through; custom error listeners that call it must pass the resolved channel rather than logging to the default.

## 7. Symfony 8.1 - `$subjectMaxLength` on MonologBridge `MailerHandler`

`Symfony\Bridge\Monolog\Handler\MailerHandler` sends buffered log records as an email. Symfony 8.1 adds a `$subjectMaxLength` constructor argument that bounds the length of the formatted email subject - long subjects (e.g. a formatted exception message used as the subject) are truncated instead of producing an oversized header.

The constructor signature in 8.1:

```php
public function __construct(
    private MailerInterface $mailer,
    callable|Email $messageTemplate,
    string|int|Level $level = Level::Debug,
    bool $bubble = true,
    private int $subjectMaxLength = 200,   // 8.1 NEW
)
```

Behavior of `$subjectMaxLength` (from the class PHPDoc): it is "the maximum number of characters of the formatted subject; pass `0` to disable truncation, otherwise the value must be at least the length of the truncation marker." When truncation is triggered, the trailing characters are replaced with the marker, so the resulting subject never exceeds `$subjectMaxLength` characters.

```yaml
# config/packages/monolog.yaml - email critical errors with a bounded subject
when@prod:
    monolog:
        handlers:
            mail_critical:
                type: service
                id: App\Logger\CriticalMailerHandler   # a service wrapping MailerHandler
```

Rules:

1. **Set `$subjectMaxLength` deliberately when the subject is dynamic.** A formatted message used as the subject can be arbitrarily long; the default `200` keeps the header within sane limits. Pass `0` only when you control the subject and know it is bounded.
2. **A non-zero value must be at least the truncation-marker length.** Setting it to a tiny value below the marker length is rejected - keep it comfortably above the marker.
3. **Use a wrapping service for non-trivial wiring.** When configuring `MailerHandler` with a custom `$subjectMaxLength`, register it as a service and reference it via `type: service` rather than trying to express every constructor argument inline.

## 8. Version Constraints

| Package | Required |
|---|---|
| `symfony/monolog-bridge` | `^8.1` (for the `$subjectMaxLength` option on `MailerHandler`; the rest of the bridge behavior is carried from the Symfony 8 baseline) |
| `symfony/monolog-bundle` | any version compatible with Symfony 8.1 |
| `symfony/framework-bundle` | `^8.1` (for `framework.exceptions.<FQCN>.log_channel:`; the routing mechanism is unchanged from `^7.3`) |
