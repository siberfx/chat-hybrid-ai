# Changelog

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
