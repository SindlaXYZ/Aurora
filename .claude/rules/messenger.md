# Symfony Messenger - 7.3 / 7.4 / 8.1 Additions

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-22 | 2026-05-29 |

**Sources:**
* https://symfony.com/blog/new-in-symfony-8-1-messenger-improvements
* https://symfony.com/blog/new-in-symfony-7-3-messenger-improvements
* https://symfony.com/doc/8.1/messenger.html
* https://symfony.com/doc/8.1/lock.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

This stub already ships a sensible default Messenger setup (`config/packages/messenger.yaml.example`) with a Doctrine transport, retry strategy, in-memory test override, and the project's `App\Schedule` scheduler. This rule covers ONLY the features introduced in Symfony 7.3 / 7.4 / 8.1 that are NOT obvious from the default config.

## 1. What Changed in Symfony 8.1

All twelve additions in 8.1 are **additive** - existing 8.0 messenger code keeps working. Two of them ship with NEW behaviour that becomes the default (decode-failure routing through retries, deduplication-lock release on definitive failure); the other ten require explicit opt-in.

| Capability | Symfony 8.0 and earlier | Symfony 8.1 |
|---|---|---|
| Workers fetch one message at a time | One transport round-trip per message | New `--fetch-size=N` consumes N messages per round-trip (SQS / Redis / Doctrine / AMQP) |
| Service container reset cadence | After every message (default) OR `--no-reset` to disable entirely | `--no-reset=100` resets every N messages - middle ground |
| Message `type` header serialization | PHP FQCN only | `#[AsMessage(serializedTypeName: '...')]` overrides the wire type name |
| AMQP message priority | Not exposed | New `AmqpPriorityStamp` (parallels `BeanstalkdPriorityStamp`) |
| `BatchHandlerTrait` partial-flush trigger | Only when `shouldFlush()` returns true | Optional `getIdleTimeout(): ?float` - flush after N seconds of inactivity |
| PostgreSQL `LISTEN/NOTIFY` blocking | Blocked at the transport level, single queue at a time | Moved to a worker `idle` subscriber - workers check all transports before blocking |
| Message decode failures (e.g. missing class) | Silently discarded | Routed through retry + failure transport via `MessageDecodingFailedException` + `DecodeFailedMessageMiddleware` |
| Redis receiver introspection | Not implementing `ListableReceiverInterface` | `RedisReceiver::all()` + `find($id)` exposed (backed by `XRANGE`) |
| Redis Cluster discovery | Required enumerating every node | New `redis_cluster=true` DSN option - single endpoint + discovery |
| AMQP quorum-queue delay strategy | One queue per delay value (could grow unbounded) | One queue per calendar day, TTL = `1 day + delay + 10s` |
| AMQP transport with no queues (write-only / pub-sub) | Required at least one queue declaration | `queues: false` (or `[]`) - skip queue creation entirely |
| Deduplication lock lifetime after definitive failure | Held until TTL expiration (300s by default) | Released immediately when the message moves to the failure transport |

## 2. Batch Fetching Messages (`--fetch-size`)

Workers fetch multiple messages in a single transport call. Reduces network round-trips on transports that batch natively (SQS, Redis Streams, Doctrine, AMQP).

```bash
bin/console messenger:consume async --fetch-size=8
```

Rules:

1. **Match `--fetch-size` to the handler's safe parallelism.** If the handler holds 200 MB while processing, a `--fetch-size=100` worker can OOM. Start with `--fetch-size=8` and tune.
2. **Combine with `--limit=N` (already in the stub's supervisord template).** `--limit` is the worker's hard ceiling before respawn; `--fetch-size` controls each transport poll within that ceiling.
3. **Do NOT use on synchronous transports.** `sync://` ignores it. The flag is a no-op there but adds noise to logs.

## 3. Configurable Service Reset Interval (`--no-reset=N`)

The container's `kernel.reset` services (Doctrine entity manager, profiler stack, Monolog buffer, ...) are reset BETWEEN messages by default. Long-running workers can defer the reset to amortize the cost:

```bash
# default: reset services after every message
bin/console messenger:consume async

# disable resets entirely (legacy --no-reset, boolean)
bin/console messenger:consume async --no-reset

# NEW in 8.1: reset every 100 messages
bin/console messenger:consume async --no-reset=100
```

Rules:

1. **Reset every message is the safe default.** Stale Doctrine identity maps and connection state cause cross-message data corruption otherwise.
2. **`--no-reset=N` is for hot loops.** Use ONLY when (a) handlers are stateless and don't accumulate Doctrine identity map entries, and (b) profiling shows the reset is a measurable cost. Otherwise, the default is right.
3. **Never `--no-reset` (no value) in PROD.** Identity-map growth + connection state drift = memory leak + slow degradation. The new `--no-reset=N` is the middle ground that's actually safe.

## 4. Custom Serialized Type Name (`#[AsMessage(serializedTypeName:)]`)

Overrides the message's `type` header (default: PHP FQCN). Useful when consumers are non-Symfony, in different namespaces, or when the PHP class moves but the wire contract must remain stable.

```php
namespace App\Crawler\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(serializedTypeName: 'crawler.vectorization_finished')]
final readonly class VectorizationFinished
{
    public function __construct(
        public string $crawlId,
    ) {
    }
}
```

Rules:

1. **Pin once, never change.** Renaming a `serializedTypeName` orphans every in-flight message that already carries the old type. Treat it as a public contract.
2. **Use dotted lowercase namespaces.** `crawler.vectorization_finished` reads cleanly in cross-stack consumers (Go, Python, Node) that don't follow PHP class-name conventions.
3. **Pair with `class_map` on the receiving side.** When the consumer is also Symfony, register the type-name in `messenger.serializer.class_map` so deserialization picks the right class.

## 5. Per-Message Priority on AMQP (`AmqpPriorityStamp`)

RabbitMQ queues declared with `x-max-priority` deliver higher-priority messages ahead of lower ones. 8.1 exposes this through a stamp:

```php
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpPriorityStamp;

$bus->dispatch($message, [new AmqpPriorityStamp(5)]);
```

Rules:

1. **Declare the queue with `x-max-priority`.** Without it, RabbitMQ ignores per-message priority silently. Set `queue_options.arguments: ['x-max-priority': 10]` in transport config.
2. **Use a SMALL priority range.** RabbitMQ recommends 1-5; higher values increase memory cost per queue without proportional benefit.
3. **Default is 0.** Messages without the stamp sit at the lowest priority. Stamp every message that should ever overtake another, even if just with `AmqpPriorityStamp(1)`.

## 6. Idle Timeout for Batch Handlers (`BatchHandlerTrait::getIdleTimeout`)

Existing `BatchHandlerTrait` flushes when `shouldFlush()` returns true (usually "batch is full"). 8.1 adds an optional `getIdleTimeout(): ?float` method - when the worker stays idle for that many seconds, the partial batch is flushed regardless of size.

```php
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

final class IndexProductsHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    // Flush partial batches after 5 seconds of inactivity.
    private function getIdleTimeout(): ?float
    {
        return 5.0;
    }

    private function shouldFlush(): bool
    {
        return 100 <= \count($this->jobs);
    }

    // ...
}
```

Rules:

1. **Pair `shouldFlush` (size) with `getIdleTimeout` (time).** Either alone leaves a failure mode: pure size means low-traffic periods stall forever; pure time means high-traffic periods flush undersized batches.
2. **Return `null` (or omit the method) to disable.** Backwards-compatible default.
3. **`float` seconds, not milliseconds.** `0.5` = half a second; `5.0` = five seconds. Don't pass an integer expecting ms.

## 7. Non-Blocking PostgreSQL `LISTEN/NOTIFY`

The Doctrine transport's PostgreSQL `LISTEN/NOTIFY` path is now driven by a worker `idle` subscriber instead of a blocking call inside the transport. Workers iterate all transports in priority order BEFORE blocking - a slow `LISTEN` no longer starves higher-priority queues.

Transparent for callers - no API change. Symptoms it fixes:

- **Before:** worker consuming `[priority_high, priority_low]` blocks on `priority_low`'s `LISTEN` even when `priority_high` has pending messages.
- **After:** worker drains every transport's pending queue, then blocks on a single shared `idle` cycle that honors priority order.

Rule: **no migration needed.** If your worker had `priority` ordering on transports and the lower-priority Doctrine/PostgreSQL transport was stealing wakeup time, the fix is automatic on upgrade.

## 8. Decode Failures Routed Through Failure Handling

Pre-8.1: deserialization failures (missing class after a rename, malformed JSON, version skew) were logged and discarded. The message was lost.

In 8.1: failures wrap into `MessageDecodingFailedException` and travel through the normal retry + failure-transport pipeline. A new `DecodeFailedMessageMiddleware` re-attempts decoding on each retry, so a fix deployed mid-retry recovers the message:

1. Worker tries to decode → fails (e.g. `App\Message\OldName` not found).
2. Middleware wraps as `MessageDecodingFailedException`, increments retry counter.
3. Retry strategy applies (default backoff). Each retry re-runs decoding.
4. After max retries, the wrapped envelope lands in the failure transport - inspectable, retryable, removable via the standard tools.

Rules:

1. **Failure transports MUST handle raw envelopes.** If the failure transport is a queue you inspect manually, expect to see `MessageDecodingFailedException`-wrapped envelopes. They're recoverable - keep the underlying payload around.
2. **Use it as a refactor safety net.** Renaming a message class? Deploy the new class FIRST, then dispatch using the old name once to verify the retry succeeds. The middleware handles the transition.
3. **Do NOT swallow `MessageDecodingFailedException` in custom middleware.** Let it bubble to the failure transport - that's the whole point.

## 9. Listable Redis Receiver

`RedisReceiver` now implements `ListableReceiverInterface`:

```php
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

// Inject the specific receiver service by name (one per transport):
public function __construct(private ListableReceiverInterface $asyncReceiver) {}

// List pending messages (does NOT consume):
$envelopes = $this->asyncReceiver->all();

// Find a specific envelope by Redis stream id:
$envelope = $this->asyncReceiver->find('1700000000000-0');
```

Backed by Redis Stream `XRANGE` - read-only, safe to call from monitoring code without disturbing consumers.

Rules:

1. **Use for monitoring / health-check endpoints.** Not for replay - replay still goes through `messenger:failed:retry` or programmatic re-dispatch.
2. **`all()` returns ALL pending messages.** Page with `limit` argument when the queue is large; otherwise this can return thousands of envelopes per call.
3. **`find($id)` IDs come from Redis Streams, not from `Envelope::last(TransportMessageIdStamp::class)`.** They're the underlying stream entry IDs - use them when correlating with `XRANGE`-style inspection in `redis-cli`.

## 10. Force Redis Cluster via DSN (`redis_cluster=true`)

Pre-8.1: Redis Cluster transports required enumerating every node in the DSN:

```dotenv
MESSENGER_TRANSPORT_DSN=redis://redis-0:6379,redis://redis-1:6379,redis://redis-2:6379
```

In 8.1, a single endpoint + `redis_cluster=true` triggers cluster discovery:

```dotenv
MESSENGER_TRANSPORT_DSN=redis://redis-cluster:6379?redis_cluster=true
```

The Redis client connects to the named host, queries it for cluster topology, and routes subsequent commands to the right shard automatically.

Rules:

1. **Use cluster mode for production Redis deployments.** Single-instance is fine for DEV; cluster is the realistic PROD topology and the new flag makes config trivial.
2. **The endpoint MUST be a cluster node.** Pointing `redis_cluster=true` at a stand-alone Redis instance fails at first command - the server can't answer `CLUSTER SLOTS`.
3. **Use a load-balanced DNS name.** `redis-cluster` as a Kubernetes Service or AWS ElastiCache endpoint - the client only uses it for initial discovery, but if that endpoint is down on startup the worker can't bootstrap.

## 11. Delayed Quorum Queues for AMQP - Per-Day Strategy

RabbitMQ quorum queues require an explicit TTL. Pre-8.1: messenger created one delay queue per unique delay value. High-cardinality delays (e.g. "deliver in N minutes" with N varying widely) bloated the queue list.

In 8.1: one queue per calendar day, expiration = `1 day + delay + 10 seconds`. Messages scheduled for the same day share a queue; the queue survives long enough for all messages to be delivered.

Transparent - no API change. Watch:

- **RabbitMQ queue count drops** sharply if you previously had hundreds of `delays_*` queues.
- **One queue per day persists for ~2 days** (the buffer covers timezone / clock skew).

Rule: nothing to do. Just notice the queue churn dropping after upgrade.

## 12. Disable Default AMQP Queue Binding (`queues: false`)

For write-only transports (publishing to an exchange without any queues bound on the same connection - the consumers maintain their own subscriptions elsewhere):

```yaml
framework:
    messenger:
        transports:
            outgoing_events:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: 'EVENTS'
                        type: fanout
                    queues: false   # or []
```

Rules:

1. **Pair with `routing: { App\Event\X: outgoing_events }`** in the same `messenger.yaml` block. Without routing, dispatching the message does nothing useful (no queue, no binding).
2. **The application never CONSUMES this transport.** `messenger:consume outgoing_events` would error - there's no queue to read from. Exclude it from worker invocations explicitly with `--exclude-receivers=outgoing_events` (see §15).
3. **The other end (the consumers) declares queues separately.** Cross-stack pub/sub: PHP publishes to `EVENTS`, Go workers subscribe to `EVENTS` via their own AMQP client.

## 13. Deduplication Lock Released on Definitive Failure

Builds on the 7.3 `DeduplicateStamp` (see §16). Pre-8.1: when a message exhausted its retry strategy and landed in the failure transport, the dedup lock was held until its TTL expired (300 seconds by default). New dispatches with the same key were blocked, even though the original attempt was definitively done.

In 8.1: the lock is released immediately on definitive failure. A fresh dispatch can enter the queue right away.

Rules:

1. **Re-test idempotency assumptions.** If your handler relied on "dedup lock blocks dispatch until TTL" to also prevent re-running after failure, that assumption is now broken. Idempotent handlers don't need this - non-idempotent ones never should have.
2. **Combine with explicit retry-on-success deduplication.** When you DO want "no retries within X seconds even after failure", set `DeduplicateStamp::getKey()` based on a time-bucket (`'key-' . floor(time() / 60)`) instead of relying on the lock to enforce it.
3. **Failure-transport replay needs new dedup keys when appropriate.** Manually retrying a failed message via `bin/console messenger:failed:retry` will re-acquire the lock if the key is the same - the immediate release on failure does NOT extend to manual replay.

## 14. Deduplication Middleware (`DeduplicateStamp`) - Symfony 7.3+

Skips dispatching a message when a previous dispatch with the same dedup key is still in-flight. Backed by the Symfony `lock` component - the middleware acquires a short-lived lock keyed on `DeduplicateStamp::getKey()` before the message is handed to the transport.

### 14.1 Requirements

- `symfony/lock` installed and `framework.lock` configured. The stub does both (`symfony.sh::symfony_install_skeleton()` installs the package; `framework.yaml.example` sets `framework.lock.default: 'flock'`).
- A serialization-supporting lock store. `flock`, `semaphore`, `redis://...`, `postgresql://...` all qualify. The default `flock` is fine for a single container; switch to a network-shared store (`redis`, `postgresql`) for multi-server deployments.

### 14.2 Usage

```php
use App\Message\RebuildSitemapMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

final readonly class SitemapDispatcher
{
    public function __construct(private MessageBusInterface $bus) {}

    public function schedule(int $siteId): void
    {
        // If a rebuild for the same site is still queued or running, this dispatch is a no-op.
        $this->bus->dispatch(
            new RebuildSitemapMessage($siteId),
            [new DeduplicateStamp('rebuild-sitemap-' . $siteId)],
        );
    }
}
```

### 14.3 Rules

1. **Choose the dedup key carefully.** It MUST uniquely identify the work being requested. Good: `'rebuild-sitemap-' . $siteId`, `'send-welcome-email-' . $userId`, `'recompute-stats-' . $year . '-' . $month`. Bad: `'rebuild-sitemap'` (collides across sites), `uniqid()` (defeats the purpose).
2. **Lock store must outlive the message lifecycle.** Lock TTL defaults to "until the message handler completes". For long-running handlers, set a TTL on `DeduplicateStamp` explicitly: `new DeduplicateStamp('key', ttl: 3600)`. Otherwise a crashed worker leaves a stale lock that blocks future dispatches until manual cleanup.
3. **Do NOT use deduplication for "exactly-once" guarantees.** Deduplication prevents *duplicate dispatches in a window*; it is not a replacement for an idempotent handler. The handler MUST still be safe to run twice if a retry occurs.
4. **No middleware registration required.** The middleware is auto-enabled when `symfony/lock` is installed AND `framework.lock` is configured. There is nothing to add to `messenger.yaml`.
5. **Definitive-failure lock release (8.1+)** - see §13. The lock now drops as soon as the message moves to the failure transport; only the in-flight retry window holds the lock.

## 15. Worker Operation Flags

### 15.1 `messenger:consume --keepalive` (Symfony 7.3+)

For Doctrine transport on long-running handlers (>30s): periodically updates the `delivered_at` timestamp on the in-flight row so the message is not re-delivered to a second worker.

```bash
bin/console messenger:consume async --keepalive=15 --limit=10 --time-limit=3600 -vv
```

Use it whenever a handler can take longer than the transport's redelivery threshold (default 30s for `doctrine://`).

### 15.2 `messenger:consume --exclude-receivers` (Symfony 7.4+)

When consuming all transports with `--all`, skip specific ones:

```bash
bin/console messenger:consume --all --exclude-receivers=failed --exclude-receivers=scheduler_default
```

Useful when one worker process consumes a normal transport set but the scheduler runs in a dedicated process (the stub's pattern via supervisord). Also the right flag for the `queues: false` pattern from §12 - the write-only transport has nothing to consume.

### 15.3 `messenger:consume --fetch-size` and `--no-reset=N` (Symfony 8.1)

See §2 and §3.

### 15.4 `messenger:failed:remove --class-filter` (Symfony 7.3+)

Purge failed messages of a specific class without touching the rest:

```bash
bin/console messenger:failed:remove --class-filter='App\Message\RebuildSitemapMessage' --force
```

Use after a fix has been deployed that supersedes any pending retries.

## 16. `RunProcessMessage::fromShellCommandline()` (Symfony 7.3+)

When a built-in `RunProcessMessage` must include shell features (pipes, redirections), use the factory instead of constructing an argument array manually:

```php
use Symfony\Component\Process\Messenger\RunProcessMessage;

$bus->dispatch(RunProcessMessage::fromShellCommandline(
    'php bin/console app:export-orders | gzip > var/exports/orders.csv.gz'
));
```

Plain argument-array `new RunProcessMessage([...])` does NOT interpret shell metacharacters. Use this factory ONLY for shell-tied commands; for everything else, prefer the array form (safer against argument injection).

## 17. Operating a Worker - Supervisord vs Cron

The stub does NOT ship a supervisord program for `messenger:consume`. Most projects run with a synchronous transport or with the scheduler-only worker, so a default worker would be wrong. When async messaging is genuinely used, add a supervisord program at `.docker/container/etc/supervisor/program/messenger.conf`:

```ini
[program:messenger]
command=/usr/bin/php /srv/%(ENV_DKZ_DOMAIN)s/bin/console messenger:consume async --keepalive=15 --fetch-size=8 --no-reset=100 --limit=10 --time-limit=3600 --memory-limit=128M -vv
user=www-data
autostart=true
autorestart=true
startretries=3
stopwaitsecs=20
stopsignal=TERM
stdout_logfile=/srv/%(ENV_DKZ_DOMAIN)s/.docker/.logs/messenger/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stderr_logfile=/srv/%(ENV_DKZ_DOMAIN)s/.docker/.logs/messenger/worker.error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=5
```

Rules:

1. **`--time-limit=3600` + `autorestart=true` is the safe pairing.** The worker recycles every hour to free leaked memory; supervisord respawns immediately.
2. **`stopsignal=TERM` + `stopwaitsecs=20`.** Symfony Messenger drains the current message on SIGTERM. 20s is enough for typical handlers; bump if your handlers take longer.
3. **`--keepalive=15` matches the recommendation in §15.1** - required when using Doctrine transport and any handler can exceed 30s.
4. **`--fetch-size=8` is the conservative 8.1 default.** Start there; raise it ONLY after profiling shows the worker is bottlenecked on transport polling, not on handler work.
5. **`--no-reset=100` is opt-in** (§3). Drop it from the line if any handler accumulates state in `kernel.reset` services.
6. **Logs go under `.docker/.logs/messenger/`** - same pattern as the rest of the stub. The directory is auto-created by the stub's log path conventions.
7. **For maintenance crons** (`messenger:failed:remove --class-filter=...`, `messenger:stop-workers`), see the commented examples in `/srv/${DKZ_DOMAIN}/cron.tab`. Both are disabled by default; uncomment per project.

## 18. Anti-patterns

- **`--no-reset` (no value) in PROD** - keeps stale identity maps across messages. Always use `--no-reset=N` if at all, never the bare flag.
- **`--fetch-size=100` without profiling memory cost per handler** - one OOM-recycled worker per hour wipes any throughput gain.
- **Renaming `serializedTypeName` after release** - breaks every in-flight message carrying the old type.
- **Per-message priority on a queue without `x-max-priority`** - RabbitMQ ignores the stamp silently; no error, no benefit.
- **`getIdleTimeout` without `shouldFlush`** - all batches flush on idle, never on size. You lost the batch optimization.
- **Catching `MessageDecodingFailedException` in user middleware** - prevents the message from reaching the failure transport. Let it bubble.
- **Consuming a `queues: false` transport** - there's no queue to read from. Use `--exclude-receivers` with `--all`.
- **Relying on `DeduplicateStamp` for retry suppression after failure** - the 8.1 lock-release behavior breaks that assumption; idempotency must be in the handler.
- **`redis_cluster=true` pointed at a single-instance Redis** - bootstraps but every command fails. Use a real cluster endpoint.

## 19. Migration Notes (8.0 → 8.1)

No required migration. Adopt incrementally:

1. **Add `--fetch-size=N` and `--no-reset=N` to worker invocations** when profiling shows transport polling or container reset is a measurable cost.
2. **Replace per-delay-value AMQP delay queues** - happens automatically; remove old queues from the broker after the upgrade is fully rolled out.
3. **Drop manual decode-failure logging** in custom middleware - the framework handles it through retry now.
4. **Switch Redis Cluster DSNs from enumerated nodes to `?redis_cluster=true`** when the DNS endpoint can be resolved cluster-wide.
5. **Audit `DeduplicateStamp` consumers** - confirm handler idempotency, because the dedup window no longer covers post-failure replay.

## 20. Version Constraints

| Package | Required |
|---|---|
| `symfony/messenger` | `^7.3` (for `DeduplicateStamp`, `--keepalive`, `--class-filter`), `^7.4` (for `--exclude-receivers`), `^8.1` (for `--fetch-size`, `--no-reset=N`, `AsMessage(serializedTypeName:)`, `AmqpPriorityStamp`, `BatchHandlerTrait::getIdleTimeout`, decode-failure routing, listable Redis receiver, `redis_cluster=true`, per-day quorum-queue delay, `queues: false`, definitive-failure lock release) |
| `symfony/lock` | `^7.3` (already installed by `symfony_install_skeleton()`) |
| `symfony/process` | `^7.3` (already installed; required for `RunProcessMessage::fromShellCommandline()`) |
