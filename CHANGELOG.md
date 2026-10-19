# Changelog

## 2.0.0 - 2026-10-19

### Added
- `laravel-ai` driver on the Laravel AI SDK (`laravel/ai`): Anthropic, OpenAI, Gemini, Groq, Mistral, DeepSeek, xAI, Ollama, OpenRouter, Azure, Bedrock, Cohere and any OpenAI-compatible API, with failover between providers (`HYBRID_CHAT_AI_PROVIDERS=anthropic,openai`).
- AI tools that read your data: `SearchRecords` (site search, LIKE or Scout), `FindRecord` (one record, scoped to the signed-in visitor or confirmed by guests), `Check` (any closure). Any laravel/ai tool also works, including `WebSearch` and `SimilaritySearch`. Register them with `HybridChat::tools([...])` or `ai.tools`.
- `request_human` tool: the agent can hand the chat to a person.
- Signed visitor identity: `<x-chat-hybrid-ai-bubble :identify="auth()->user()" />`, `HybridChat::identity($user)`, and the `identity` option in the JS client and React/Vue widgets. Stored as `visitor_id`; tools trust only this.
- Admins see the AI's lookups under each reply (`tools` on admin messages) and the signed-in user in the visitor details.
- `ChatContext` for tools, `SupportAgent::fake()` for tests, `models.visitor` and `identity.ttl` config.

### Changed
- **Breaking:** the `anthropic` and `openai` drivers are removed, replaced by `laravel-ai`, the new default. `anthropic-ai/sdk` is no longer required. See "Upgrading from 1.x" in the README.
- **Breaking:** new migration adding `visitor_id` to conversations.

## 1.1.0 - 2026-10-19

- Expanded README with full examples: curl walkthroughs of the visitor and admin APIs, Sanctum tokens, webhook receivers (Laravel, Node), a custom driver, React and Vue custom UIs and admin inboxes, Echo listeners, events, custom models, and a full `.env` reference.
- CI uses `actions/checkout@v5`.

## 1.0.0 - 2026-10-19

First stable release.

- Requires PHP 8.3+ and Laravel 13.30+.
- Visitor API and chat bubble (one `<script>` tag, Shadow DOM).
- Admin inbox at `/chat-admin`, plus a token-authenticated admin API at `api/chat-admin` (on when Laravel Sanctum is installed) with a new `GET config` endpoint.
- React and Vue widgets and hooks on a dependency-free JS client (`ChatClient`, `ChatSession`, `AdminClient`), publishable with `--tag=chat-hybrid-ai-js`.
- AI drivers: Claude (official Anthropic SDK), OpenAI-compatible APIs, webhook, custom.
- Human handoff, email transcripts, optional broadcasting.
