# Chat Hybrid AI

API-first live chat for Laravel 13.30+ (PHP 8.3+). Inspired by [siberfx/laravel-messenger](https://github.com/siberfx/laravel-messenger), rebuilt for website support chat:

- **Chat bubble** for any site: one `<script>` tag, no build step, Shadow DOM so your CSS can't break it.
- **Admin inbox** at `/chat-admin` with filters (needs a human, mine, with AI, unread, closed), search, and visitor details.
- **AI agent** answers first. It can hand off to a human, the visitor can ask for one, and an admin can take over or hand back at any time. Drivers: Claude (official Anthropic SDK), any OpenAI-compatible API, your own webhook agent, or a custom driver.
- **Email transcripts**: the visitor can email the chat to themselves, admins can send it to any address, and it can go out automatically on close.
- **REST API** for everything: a public visitor API and an admin API that works with the session or with Sanctum tokens, so you can build your own front end or mobile app.
- **React and Vue** components and hooks ship with the package, on top of a dependency-free JS client.

## Install

```bash
composer require siberfx/chat-hybrid-ai
php artisan migrate
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=chat-hybrid-ai-config
```

## 1. Add the bubble

In a Blade layout:

```blade
<x-chat-hybrid-ai-bubble />

{{-- Prefill a logged-in user and pass context the agent and admins will see --}}
<x-chat-hybrid-ai-bubble
    :name="auth()->user()?->name"
    :email="auth()->user()?->email"
    :meta="['plan' => 'pro', 'order' => '#1042']"
/>
```

On any other site (static HTML, WordPress, ...):

```html
<script src="https://your-app.com/api/chat/widget.js" defer></script>
```

Optional attributes: `data-name`, `data-email`, `data-color`, `data-title`, `data-meta='{"plan":"pro"}'`, `data-api`.
From JavaScript: `HybridChat.open()`, `HybridChat.close()`, `HybridChat.toggle()`.

> Embedding on another domain? Allow it in `config/cors.php`: add `api/chat/*` to `paths`.

## 2. Give admins access

The inbox lives at `/chat-admin` behind `web` + `auth` and the `viewHybridChat` gate. Like Horizon, the default gate only allows the `local` environment. Define your own in `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewHybridChat', fn ($user) => $user->is_admin);
```

## 3. Connect an AI agent

AI replies run in a queued job (`GenerateAiReply`), so run a queue worker.

### Claude (default)

```dotenv
HYBRID_CHAT_AI_DRIVER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
# Optional, defaults to claude-opus-5-5
HYBRID_CHAT_ANTHROPIC_MODEL=claude-opus-5-5
```

The driver uses the official `anthropic-ai/sdk`. It runs at `effort: low`, which suits chat. It also turns on the server-side refusal fallback (`fallbacks: "default"`), so if the model declines a message the API retries on a fallback model. If it still declines, the chat is handed to a human. To turn the fallback off, set `ai.drivers.anthropic.fallbacks` to `null`.

### Any OpenAI-compatible API (OpenAI, OpenRouter, Groq, Ollama, LM Studio, ...)

```dotenv
HYBRID_CHAT_AI_DRIVER=openai
HYBRID_CHAT_OPENAI_URL=https://api.openai.com/v1
HYBRID_CHAT_OPENAI_KEY=...
HYBRID_CHAT_OPENAI_MODEL=...
```

### Your own agent (webhook)

```dotenv
HYBRID_CHAT_AI_DRIVER=webhook
HYBRID_CHAT_WEBHOOK_URL=https://agents.example.com/support
HYBRID_CHAT_WEBHOOK_SECRET=change-me
```

The package POSTs JSON to your URL:

```json
{
  "conversation": { "id": "uuid", "visitor_name": "Vera", "visitor_email": "vera@example.com", "subject": null, "meta": {} },
  "system_prompt": "...",
  "messages": [ { "role": "user", "content": "Do you ship to Turkey?" } ]
}
```

The `X-Chat-Signature` header holds `hash_hmac('sha256', $rawBody, $secret)`. Respond with:

```json
{ "reply": "Yes, in 3–5 days.", "handoff": false }
```

Return `"reply": null` to stay silent, or `"handoff": true` to call a human.

### A custom driver

```php
use Siberfx\ChatHybridAi\Facades\HybridChat;

HybridChat::extendAi('my-agent', fn ($app, array $config) => new MyAgent($config));
```

`MyAgent` implements `Siberfx\ChatHybridAi\Contracts\AiAgent`. It receives the conversation, the user/assistant turns (oldest first, ending with the visitor), and the system prompt, and returns an `AiReply` or `null`. Set `HYBRID_CHAT_AI_DRIVER=my-agent`; its config comes from `ai.drivers.my-agent`.

### Handoff to a human

A conversation is in one of three modes:

| mode | who answers | how it gets there |
|---|---|---|
| `ai` | the AI agent | new chats (when AI is on); an admin clicks **Hand back to AI** |
| `waiting` | nobody yet: it appears under **Needs a human** | the visitor clicks **Talk to a person**; the AI ends its reply with `[[HANDOFF]]`; the model refuses; or the AI errors |
| `human` | an admin | an admin clicks **Take over** or simply replies |

The default system prompt tells the model when to use the `[[HANDOFF]]` marker. Change it with `HYBRID_CHAT_AI_PROMPT` or `ai.system_prompt`. To email a team when a chat needs a person, set `HYBRID_CHAT_NOTIFY=support@example.com,lead@example.com`.

With `HYBRID_CHAT_AI=false`, every chat starts in `waiting`, and the package works as a normal human live chat.

## Transcripts

- Visitor: menu → **Email me this chat**. It only goes to the address on the conversation, so the endpoint can't be used to send mail anywhere else, and it is rate-limited.
- Admin: **Email transcript** → any addresses.
- Automatically on close:

```dotenv
HYBRID_CHAT_TRANSCRIPT_VISITOR=true
HYBRID_CHAT_TRANSCRIPT_TO=archive@example.com
```

Mails are queued by default (`transcripts.queue`). Publish the views (`--tag=chat-hybrid-ai-views`) to restyle them.

## REST API

### Widget (public, `api/chat`)

Every `conversations/{id}/*` call needs the `X-Chat-Token` header returned by `POST conversations`.

| Method | Path | Body / query |
|---|---|---|
| GET | `widget.js` | the bubble script |
| GET | `config` | widget settings |
| POST | `conversations` | `name?`, `email?`, `subject?`, `message?`, `meta?` → `{token, conversation, messages}` |
| GET | `conversations/{id}` | conversation + all messages |
| PATCH | `conversations/{id}` | `name?`, `email?` |
| GET | `conversations/{id}/messages` | `?after={lastMessageId}`, for polling |
| POST | `conversations/{id}/messages` | `body` |
| POST | `conversations/{id}/read` | |
| POST | `conversations/{id}/human` | ask for a person |
| POST | `conversations/{id}/close` | |
| POST | `conversations/{id}/transcript` | `email?` |

### Admin (`chat-admin/api`, session auth + gate)

| Method | Path | Body / query |
|---|---|---|
| GET | `config` | `poll_interval`, `ai` |
| GET | `stats` | counts: open, ai, waiting, human, unread |
| GET | `conversations` | `?status=open\|closed&mode=ai\|waiting\|human&mine=1&unread=1&search=&per_page=` |
| GET | `conversations/{id}` | marks it read |
| GET | `conversations/{id}/messages` | `?after=` |
| POST | `conversations/{id}/messages` | `body`, takes the chat over |
| POST | `conversations/{id}/take-over` · `return-to-ai` · `close` · `reopen` | |
| POST | `conversations/{id}/transcript` | `emails[]?` (defaults to the visitor) |
| DELETE | `conversations/{id}` | soft delete |

### Admin with tokens (`api/chat-admin`)

The same admin endpoints as above, for SPAs and mobile apps that send a bearer token. It is on when [Laravel Sanctum](https://laravel.com/docs/sanctum) is installed (`php artisan install:api`), and it runs behind `api` + `auth:sanctum` and the `viewHybridChat` gate:

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"      https://your-app.com/api/chat-admin/conversations?mode=waiting
```

Turn it on or off with `HYBRID_CHAT_ADMIN_API=true|false`. Change the prefix or the guard in `routes.admin_api`.

## React and Vue

The package ships a dependency-free API client, plus a hook and a ready-made widget for React and for Vue. Copy them into your app and edit them:

```bash
php artisan vendor:publish --tag=chat-hybrid-ai-js
```

They are copied to `resources/js/vendor/chat-hybrid-ai/`:

| File | What it is |
|---|---|
| `client/hybrid-chat.js` | `ChatClient` (visitor API), `ChatSession` (polling, unread count, resume after reload), `AdminClient` (admin API) |
| `client/chat-widget.css` | styles shared by both widgets; set `--hc-color` to restyle |
| `react/useHybridChat.js`, `react/ChatWidget.jsx` | React 18+ hook and widget |
| `vue/useHybridChat.js`, `vue/ChatWidget.vue` | Vue 3 composable and widget |

### React

```jsx
import ChatWidget from './vendor/chat-hybrid-ai/react/ChatWidget.jsx';

<ChatWidget api="/api/chat" name={user?.name} email={user?.email} meta={{ plan: 'pro' }} />
```

To build your own UI, use the hook:

```jsx
import { useHybridChat } from './vendor/chat-hybrid-ai/react/useHybridChat.js';

function SupportPanel() {
    const chat = useHybridChat({ api: '/api/chat' });

    return (
        <>
            {chat.messages.map((m) => <p key={m.id}><b>{m.sender_type}</b> {m.body}</p>)}
            <button onClick={() => chat.send('Hi!')}>Say hi</button>
            {chat.conversation?.mode === 'ai' && <button onClick={chat.requestHuman}>Talk to a person</button>}
        </>
    );
}
```

The hook returns `ready`, `config`, `conversation`, `messages`, `open`, `unread`, `sending`, `awaitingReply`, `error` and the actions `send(body, { name, email })`, `setOpen(bool)`, `requestHuman()`, `emailTranscript(email)` and `end()`.

### Vue

```vue
<script setup>
import ChatWidget from './vendor/chat-hybrid-ai/vue/ChatWidget.vue';
</script>

<template>
    <ChatWidget api="/api/chat" :name="user?.name" :email="user?.email" :meta="{ plan: 'pro' }" />
</template>
```

`useHybridChat()` returns the same actions, and the state as a `shallowRef`: `state.value.messages`, `state.value.conversation`, and so on.

### An admin app

```js
import { AdminClient } from './vendor/chat-hybrid-ai/client/hybrid-chat.js';

const admin = new AdminClient({ api: 'https://your-app.com/api/chat-admin', token: sanctumToken });

const { data } = await admin.conversations({ mode: 'waiting' });
await admin.send(data[0].id, 'Hi, I can help with that.');
await admin.close(data[0].id);
```

In a same-site SPA that uses the session, use `new AdminClient({ api: '/chat-admin/api', credentials: 'include', headers: { 'X-XSRF-TOKEN': xsrf } })` instead.

## Using it from code

```php
use Siberfx\ChatHybridAi\Facades\HybridChat;

$chat = HybridChat::start(['visitor_name' => 'Vera', 'visitor_email' => 'vera@example.com'], 'Hello!');
HybridChat::agentMessage($chat, $user, 'Hi Vera!');
HybridChat::requestHuman($chat);
HybridChat::returnToAi($chat);
HybridChat::close($chat);
HybridChat::sendTranscript($chat, ['someone@example.com']);
```

Events: `ConversationStarted`, `MessageCreated`, `HandoffRequested` (`reason`: `visitor`, `ai`, `refusal`, `ai_error`), `ConversationClosed`. Use them for Slack or Telegram alerts, CRM sync, and so on.

## Real-time (optional)

The bubble and inbox poll every `widget.poll_interval` ms (3 s by default, slower when closed or in a background tab). If you run Reverb or Pusher, set `HYBRID_CHAT_BROADCAST=true` and `MessageCreated` is broadcast as `message.created` on:

- `chat.conversation.{uuid}`: a public channel (the uuid is unguessable)
- `chat.admin`: a private channel, authorised by the `viewHybridChat` gate

## Configuration reference

See [`config/chat-hybrid-ai.php`](config/chat-hybrid-ai.php): models and tables, route prefixes and middleware, widget texts, colour and position, AI driver settings, transcripts and notifications.

## Testing

```bash
composer test
```
