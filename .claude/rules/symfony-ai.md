# Symfony AI (v0.9.x)

| Version | Created    | Updated    |
|---------|------------|------------|
| 0.9     | 2026-05-18 | 2026-05-29 |

**Sources:**
* https://ai.symfony.com/
* https://symfony.com/doc/current/ai/bundles/ai-bundle.html
* https://symfony.com/doc/current/ai/components/platform.html
* https://github.com/symfony/ai
* https://github.com/symfony/ai-demo
* https://github.com/symfony/ai-bundle
* https://github.com/symfony/ai-platform
* https://github.com/symfony/ai-anthropic-platform
* https://github.com/symfony/ai-gemini-platform
* https://github.com/symfony/ai-mistral-platform
* https://github.com/symfony/ai-open-ai-platform
* https://repo.packagist.org/p2/symfony/ai-bundle.json

`symfony/ai` is a set of packages under the Symfony umbrella for building AI-driven features: LLM inference, vector stores for RAG, autonomous agents, persistent chat context, and Model Context Protocol (MCP) integration. Still at v0.9.x (verified on Packagist; no release above 0.9 exists yet). The current `symfony/ai-bundle` requires `symfony/framework-bundle: ^7.3|^8.0`, and the `^8.0` constraint covers Symfony 8.1 - so the bundle runs unchanged on the v8.1 stub (PHP 8.5, Symfony 8.1).

**The stub installs the AI Bundle and four provider bridges by default** - `symfony_install_skeleton()` in `.docker/container/scripts/symfony.sh` runs `composer require symfony/ai-bundle:^0.9` together with the OpenAI, Anthropic, Mistral and Gemini bridges. Configuration lives in `config/packages/ai.yaml` (renamed from `ai.yaml.example`), and a reference command exists at `src/Command/AiCommand.php`.

## 1. Packages Installed by the Stub

| Package                              | Role                                                         |
|--------------------------------------|--------------------------------------------------------------|
| `symfony/ai-bundle`                  | Symfony integration: services, profiler panel, YAML config   |
| `symfony/ai-platform`                | Core `PlatformInterface` + `Message` / `MessageBag`          |
| `symfony/ai-agent`                   | `AgentInterface` - Platform + Store + tool calls             |
| `symfony/ai-chat`                    | Persistent chat context (history, system prompt, memory)     |
| `symfony/ai-open-ai-platform`        | OpenAI bridge (GPT, embeddings, DALL-E, Whisper, TTS)        |
| `symfony/ai-anthropic-platform`      | Anthropic bridge (Claude family)                             |
| `symfony/ai-gemini-platform`         | Google bridge (Gemini family + embeddings)                   |
| `symfony/ai-mistral-platform`        | Mistral bridge (mistral-*, codestral-*, ministral-*, pixtral-*) |

The `symfony/ai-store` (vector stores for RAG) and `symfony/mcp-bundle` (MCP server / client) packages are NOT installed by default - opt in per project when RAG or MCP is needed.

Bundle FQCN registered in `config/bundles.php`: `Symfony\AI\AiBundle\AiBundle`.

## 2. Auto-Registered Service IDs

The bundle reads `config/packages/ai.yaml` and registers one service per declared key:

| YAML key                    | Service id pattern              | Interface              | Stub autowire example                       |
|-----------------------------|---------------------------------|------------------------|---------------------------------------------|
| `ai.platform.<name>`        | `ai.platform.openai`            | `PlatformInterface`    | `PlatformInterface $openai`                 |
| `ai.agent.<name>`           | `ai.agent.default`              | `AgentInterface`       | `AgentInterface $defaultAgent`              |
| `ai.store.<type>.<name>`    | `ai.store.postgres.symfony_blog`| `StoreInterface`       | `StoreInterface $symfonyBlog`               |
| `ai.vectorizer.<name>`      | `ai.vectorizer.openai`          | `VectorizerInterface`  | `VectorizerInterface $openai`               |
| `ai.indexer.<name>`         | `ai.indexer.blog`               | `IndexerInterface`     | `IndexerInterface $blog`                    |
| `ai.retriever.<name>`       | `ai.retriever.blog`             | `RetrieverInterface`   | `RetrieverInterface $blog`                  |
| `ai.message_store.<...>`    | `ai.message_store.cache.foo`    | `MessageStoreInterface`| `MessageStoreInterface $foo`                |
| `ai.chat.<name>`            | `ai.chat.youtube`               | `ChatInterface`        | `ChatInterface $youtube`                    |
| `ai.multi_agent.<name>`     | `ai.multi_agent.support`        | `AgentInterface`       | `AgentInterface $supportMultiAgent`         |

The parameter-name aliasing is provided by the bundle's container extension - if autowiring by parameter name fails, fall back to explicit `#[Autowire(service: 'ai.platform.openai')]` (the pattern used in `src/Command/AiCommand.php`).

## 3. Canonical Model Identifiers

Source: each bridge's `ModelCatalog.php` (e.g. `vendor/symfony/ai-open-ai-platform/ModelCatalog.php`). Choose models in ai.yaml from this list - typos fail at request time with `Unknown model "..."`. The stub's `ai.yaml.example` already picks from these (`gpt-4o-mini`, `claude-3-7-sonnet-latest`, `gemini-2.5-flash`, `mistral-small-latest`).

### OpenAI (`ai.platform.openai`)

| Capability             | Recommended pick (cost/perf)       | Other available identifiers                                                                 |
|------------------------|------------------------------------|---------------------------------------------------------------------------------------------|
| Chat (cheap)           | `gpt-4o-mini`                      | `gpt-4o`, `gpt-4.1-mini`, `gpt-5-mini`, `gpt-5-nano`                                        |
| Chat (high quality)    | `gpt-4.1`                          | `gpt-5`, `gpt-5-chat-latest`, `gpt-4.5-preview`                                             |
| Reasoning              | `o3-mini`                          | `o3`, `o3-mini-high`                                                                        |
| Embeddings             | `text-embedding-3-small`           | `text-embedding-3-large`, `text-embedding-ada-002`                                          |
| Text-to-Speech         | `tts-1`                            | `tts-1-hd`, `gpt-4o-mini-tts`                                                               |
| Speech-to-Text         | `whisper-1`                        | -                                                                                            |
| Image gen              | `dall-e-3`                         | `dall-e-2`                                                                                  |

### Anthropic Claude (`ai.platform.anthropic`)

| Tier        | Use for                            | Identifier                              |
|-------------|------------------------------------|-----------------------------------------|
| Cheap/fast  | Bulk classification, summaries     | `claude-3-5-haiku-latest`               |
| Mid         | Default chat                       | `claude-3-5-sonnet-latest` (or pin date `claude-3-5-sonnet-20241022`) |
| Mid-new     | Reasoning + tool-use chat          | `claude-3-7-sonnet-latest`              |
| Mid-new     | Tool-use chat (Claude 4 line)      | `claude-sonnet-4-0`, `claude-sonnet-4-20250514` |
| Top         | Complex reasoning, code, long-form | `claude-opus-4-1`, `claude-opus-4-0`    |

PHP constants: `Symfony\AI\Platform\Bridge\Anthropic\Claude::HAIKU_35`, `SONNET_35`, `SONNET_37`, `SONNET_4_0`, `OPUS_4_1`, etc.

### Google Gemini (`ai.platform.gemini`)

| Tier          | Use for                          | Identifier                              |
|---------------|----------------------------------|-----------------------------------------|
| Cheap/fast    | Bulk inference, embeddings hop   | `gemini-2.5-flash-lite`                 |
| Mid (default) | General chat                     | `gemini-2.5-flash`                      |
| High          | Complex reasoning                | `gemini-2.5-pro`                        |
| Multi-modal   | Image + text + audio             | `gemini-2.5-flash-image`                |
| Embeddings    | RAG vectorization                | `gemini-embedding-001`                  |
| TTS           | Native audio generation          | `gemini-2.5-flash-preview-tts`          |

### Mistral (`ai.platform.mistral`)

| Tier        | Use for                          | Identifier                              |
|-------------|----------------------------------|-----------------------------------------|
| Cheap/fast  | Bulk inference                   | `mistral-small-latest`                  |
| Mid         | General chat                     | `mistral-medium-latest`                 |
| Top         | Complex reasoning                | `mistral-large-latest`                  |
| Code        | Code generation / completion     | `codestral-latest`                      |
| Multi-modal | Image + text                     | `pixtral-large-latest`, `pixtral-12b-latest` |
| Embeddings  | RAG vectorization                | `mistral-embed`                         |

## 4. Two API Levels - Platform vs Agent

| Level    | Class                  | When to use                                                          |
|----------|------------------------|----------------------------------------------------------------------|
| Platform | `PlatformInterface`    | One-off LLM call. You pick the model id per call. Full control.      |
| Agent    | `AgentInterface`       | Pre-configured (model + system prompt + tools) in `ai.yaml`. Reusable. |

### Platform (low-level)

```php
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

final readonly class SummaryService
{
    public function __construct(
        #[Autowire(service: 'ai.platform.anthropic')] private PlatformInterface $anthropic,
    ) {}

    public function summarize(string $text): string
    {
        $messages = new MessageBag(
            Message::forSystem('You are a concise summariser. 1-2 sentences max.'),
            Message::ofUser($text),
        );

        return (string) $this->anthropic->invoke('claude-3-5-sonnet-latest', $messages)->asText();
    }
}
```

### Agent (high-level, declarative)

`ai.yaml`:

```yaml
ai:
    agent:
        summary:
            platform: 'ai.platform.anthropic'
            model:    'claude-3-5-sonnet-latest'
            prompt:   'You are a concise summariser. 1-2 sentences max.'
            tools:    false
```

```php
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final readonly class SummaryService
{
    public function __construct(
        #[Autowire(service: 'ai.agent.summary')] private AgentInterface $summary,
    ) {}

    public function summarize(string $text): string
    {
        return (string) $this->summary->call(new MessageBag(Message::ofUser($text)))->asText();
    }
}
```

Pick Agent when the (model + system prompt + tools) combo is fixed and shared across many call sites. Pick Platform when each call needs a different model or system prompt.

## 5. Rules

1. **Pin a model ID per environment.** `*-latest` aliases drift between vendor releases. CI / production should reference an explicit dated model (`claude-3-5-sonnet-20241022`, not `claude-3-5-sonnet-latest`). DEV may use `*-latest` for convenience.
2. **Treat the platform as a network dependency.** Wrap calls in retries with exponential backoff, log failures, and never block a critical request path on a slow inference. Use `symfony/messenger` to push long inferences to a worker.
3. **Cache predictable outputs.** Determinism is low by default - but for structured-output calls with `temperature: 0`, cache by input hash for cost control. The bundle does NOT cache responses automatically.
4. **Secrets via env vars.** API keys live in `.env.local` (per-project) - NEVER commit them. The bundle reads `%env(OPENAI_API_KEY)%` / `%env(ANTHROPIC_API_KEY)%` / `%env(GEMINI_API_KEY)%` / `%env(MISTRAL_API_KEY)%` references from `config/packages/ai.yaml`.
5. **Use `Message::forSystem()` for instructions, not user messages.** Vendors give system messages higher trust and stronger steering. A user message asking "act as a JSON validator" is easier to ignore or jailbreak than the same content in a system message.
6. **Profile cost.** The bundle's profiler panel shows token usage per request. Use it in DEV to catch runaway prompts BEFORE they hit production.
7. **MCP server endpoints are AUTHN'd.** Exposing project capabilities through MCP is a security boundary - treat the MCP server config like any other public endpoint (rate limits, auth, audit logs).

## 6. Stub Integration Pattern

When a project adds AI-driven features:

- Provider configuration: `config/packages/ai.yaml` (already populated with OpenAI / Anthropic / Gemini / Mistral platforms and example agents).
- Service classes that call platforms / agents live under `src/Service/AI/`.
- Vector-store indexing (when `symfony/ai-store` is added) lives under `src/Service/AI/Indexer/`.
- MCP tools (server-side capabilities exposed via MCP, when `symfony/mcp-bundle` is added) live under `src/Service/AI/Tool/`.
- Reference command pattern: `src/Command/AiCommand.php` - uses explicit `#[Autowire(service: '...')]` to access multiple platforms / agents simultaneously.

## 7. Reference Command - `app:ai`

The stub ships `src/Command/AiCommand.php` as a recipe book - one `--action=*` per pattern:

```bash
# Single-provider Platform-level calls
bin/console app:ai --action=simple-openai
bin/console app:ai --action=simple-claude
bin/console app:ai --action=simple-gemini
bin/console app:ai --action=simple-mistral

# Compare all 4 providers on the same prompt
bin/console app:ai --action=compare-all
bin/console app:ai --action=compare-all --prompt="Define dependency injection in one sentence."

# Higher-level Agent API (uses config from ai.yaml)
bin/console app:ai --action=agent-default
bin/console app:ai --action=agent-openai
bin/console app:ai --action=agent-claude
bin/console app:ai --action=agent-gemini
bin/console app:ai --action=agent-mistral

# Advanced patterns
bin/console app:ai --action=system-prompt        # Message::forSystem + Message::ofUser
bin/console app:ai --action=multi-turn           # MessageBag history for follow-up turns
bin/console app:ai --action=streaming            # Token-by-token streaming (OpenAI)
bin/console app:ai --action=structured-output    # JSON Schema constrained output

# Dry run (skip the network call, useful for CI smoke tests)
bin/console app:ai --action=compare-all --dry-run=1
```

When extending: keep the one-action-per-pattern style - readers should be able to grep `app:ai --action=` and find a working example for any feature they need.

## 8. Bundle Console Commands

`symfony/ai-bundle` registers its own console commands in addition to the stub's `app:ai`:

```bash
# Direct platform invocation, bypassing config
bin/console ai:platform:invoke openai gpt-4o-mini "Hello, world!"
bin/console ai:platform:invoke anthropic claude-3-5-sonnet-latest "Hello!"

# Interactive chat with a configured agent
bin/console ai:agent:call default

# When using symfony/ai-store (RAG):
bin/console ai:store:setup chromadb.default
bin/console ai:store:index default
bin/console ai:store:drop chromadb.default --force
```

Use `ai:platform:invoke` for ad-hoc "is the API key correct?" smoke tests; use `app:ai` for repeatable scripted demos.

## 9. Streaming and Structured Output Options

### Streaming

```php
$result = $platform->invoke($model, $messages, ['stream' => true]);

foreach ($result->getContent() as $chunk) {
    echo (string) $chunk;
}
```

The `stream` option is a vendor-side flag - not every model supports it for every endpoint. Test with the target model before relying on it in production code.

### Structured output (JSON Schema)

```php
$result = $platform->invoke('gpt-4o-mini', $messages, [
    'response_format' => [
        'type'        => 'json_schema',
        'json_schema' => [
            'name'   => 'person_info',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => [
                    'name' => ['type' => 'string'],
                    'age'  => ['type' => 'integer'],
                ],
                'required'             => ['name', 'age'],
                'additionalProperties' => false,
            ],
        ],
    ],
]);

$data = $result->getContent();  // already parsed array matching the schema
```

Strict JSON Schema is currently best-supported on OpenAI. Anthropic and Gemini implement subset variants - verify the response shape with `assertSame()` in tests.

## 10. Testing

Tests must NEVER hit a real LLM API:

- Non-deterministic - output changes every run; assertions fail intermittently.
- Slow - each call adds 1-30 s of network latency to the suite.
- Expensive - paid per token on every CI run, multiplied by every PR.

The stub's `ai.yaml.example` already overrides every provider API key to the placeholder `test-no-real-call` under `when@test`. For the actual code path, replace the platform / agent service with an in-memory test double:

```php
// src/Service/AI/FakePlatform.php (lives under src/, registered only in test env)
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: 'ai.platform.openai', when: 'test')]
final class FakePlatform implements PlatformInterface
{
    public function invoke(string|Model $model, string|MessageBag $input, array $options = []): ResultInterface
    {
        return new class implements ResultInterface {
            public function asText(): string { return 'FIXED TEST RESPONSE'; }
            // ... implement other ResultInterface methods to return canned data
        };
    }
}
```

`#[AsAlias(when:)]` is a 7.3 feature documented in the v8.0 stub's `.claude/rules/dependency-injection.md` §2 (the v8.1 stub references the 7.3 / 7.4 DI patterns there); it still applies on 8.1.

## 11. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `PlatformException: 401 Unauthorized` | Missing / wrong API key in `.env.local` | Verify the right env var name (`OPENAI_API_KEY` vs `ANTHROPIC_API_KEY` vs `GEMINI_API_KEY` vs `MISTRAL_API_KEY`); test with `bin/console ai:platform:invoke <provider> <model> "ping"`. |
| `Unknown model "..."` | Typo OR model not in the bridge's `ModelCatalog` | Check the catalog list in §3; for very new model IDs upgrade the bridge package (`composer update symfony/ai-anthropic-platform`). |
| `Cannot autowire service "...": argument "$openai"` | Bundle service alias missing OR provider section absent from `ai.yaml` | Verify `ai.platform.openai:` exists in `ai.yaml`; otherwise use explicit `#[Autowire(service: 'ai.platform.openai')]`. |
| `StructuredOutputException: schema mismatch` | Model returned data that does not match the schema | Loosen the schema, raise the temperature ceiling, OR pin a stronger model (the cheapest models drift on strict JSON Schema). |
| `MCPException: unknown tool` | Tool removed from server but client cache stale | Restart the MCP client; clear any tool cache. |
| Profiler panel empty | Bundle not enabled OR request happened before bundle compiled | `bin/console cache:clear`. |
| `Cannot inject AgentInterface $missingAgent` (compile time) | Agent name in autowire doesn't match a declared `ai.agent.<name>` | Either add the agent to `ai.yaml`, OR rename the autowire to match an existing agent (`ai.agent.default`). |

## 12. Version Constraints

| Package | Required |
|---|---|
| `symfony/ai-bundle` | `^0.9` |
| `symfony/ai-platform` | `^0.9` |
| `symfony/ai-agent` | `^0.9` |
| `symfony/ai-chat` | `^0.9` |
| `symfony/ai-open-ai-platform` | `^0.9` |
| `symfony/ai-anthropic-platform` | `^0.9` |
| `symfony/ai-gemini-platform` | `^0.9` |
| `symfony/ai-mistral-platform` | `^0.9` |
| `symfony/ai-store` | `^0.9` (optional - RAG, opt-in per project) |
| `symfony/mcp-bundle` | `^0.9` (optional - MCP server / client, opt-in per project) |

Note: v0.x is pre-1.0; expect minor breaking changes between minors (e.g. provider bridges were split out from a monorepo around v0.9). Pin tightly in `composer.json` and bump deliberately. The bundle's own Symfony constraint is `symfony/framework-bundle: ^7.3|^8.0`; the `^8.0` half covers Symfony 8.1, so no `^8.1`-specific bundle release is required for the v8.1 stub.
