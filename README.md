# Chat Hybrid AI

[![tests](https://github.com/siberfx/chat-hybrid-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/siberfx/chat-hybrid-ai/actions/workflows/tests.yml)
[![release](https://img.shields.io/github/v/release/siberfx/chat-hybrid-ai)](https://github.com/siberfx/chat-hybrid-ai/releases)
[![license](https://img.shields.io/github/license/siberfx/chat-hybrid-ai)](LICENSE.md)

API-first live chat for Laravel 13.30+ (PHP 8.3+). An AI agent answers first, and a person takes over when needed. Inspired by [siberfx/laravel-messenger](https://github.com/siberfx/laravel-messenger), rebuilt for website support chat.

- **Chat bubble** for any site: one `<script>` tag, no build step, Shadow DOM so your CSS can't break it.
- **Admin inbox** at `/chat-admin` with filters (needs a human, mine, with AI, unread, closed), search, and visitor details.
- **AI agent** answers first, through the [Laravel AI SDK](https://laravel.com/docs/ai-sdk): Anthropic, OpenAI, Gemini, Groq, Mistral, DeepSeek, xAI, Ollama, OpenRouter, Azure, Bedrock and more, with failover between providers. It can hand off to a human, the visitor can ask for one, and an admin can take over or hand back at any time. A webhook driver and custom PHP drivers are also supported.
- **AI that uses your data**: give the agent tools to search your products, look up a visitor's own order, or check a coupon, without letting it write SQL. Signed visitor identities make sure it only shows people their own records.
- **Email transcripts**: the visitor can email the chat to themselves, admins can send it to any address, and it can go out automatically on close.
- **REST API** for everything: a public visitor API, plus an admin API that works with the session or with Sanctum tokens. Use it to build your own front end or mobile app.
- **React and Vue** widgets and hooks ship with the package, built on a dependency-free JS client.
- **Real-time** through Reverb or Pusher (optional; polling works out of the box).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [The chat bubble](#the-chat-bubble)
- [The admin inbox](#the-admin-inbox)
- [AI agents](#ai-agents)
- [Let the AI use your data](#let-the-ai-use-your-data)
- [Handoff to a human](#handoff-to-a-human)
- [Email transcripts](#email-transcripts)
- [REST API: visitor](#rest-api-visitor)
- [REST API: admin](#rest-api-admin)
- [JavaScript client](#javascript-client)
- [React](#react)
- [Vue](#vue)
- [Build your own admin app](#build-your-own-admin-app)
- [Real-time with Reverb or Pusher](#real-time-with-reverb-or-pusher)
- [Using it from PHP](#using-it-from-php)
- [Events](#events)
- [Custom models and tables](#custom-models-and-tables)
- [Configuration reference](#configuration-reference)
- [Security](#security)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Testing](#testing)

## Requirements

- PHP 8.3+
- Laravel 13.30+
- A queue worker (AI replies and emails run in queued jobs)
- [laravel/ai](https://github.com/laravel/ai), installed with the package
- Optional: [Laravel Sanctum](https://laravel.com/docs/sanctum) for the token admin API, and Reverb or Pusher for real-time updates

## Installation

```bash
composer require siberfx/chat-hybrid-ai
php artisan migrate
```

This creates two tables: `chat_conversations` and `chat_messages`.

AI providers and their keys live in laravel/ai's `config/ai.php`. Publish it to change providers, URLs or the default:

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

Publish only what you want to change:

```bash
php artisan vendor:publish --tag=chat-hybrid-ai-config      # config/chat-hybrid-ai.php
php artisan vendor:publish --tag=chat-hybrid-ai-views       # bubble tag, admin page, mail views
php artisan vendor:publish --tag=chat-hybrid-ai-migrations  # to edit the migration before running it
php artisan vendor:publish --tag=chat-hybrid-ai-js          # React/Vue widgets and the JS client
```

AI replies are generated in a queued job, so run a worker:

```bash
php artisan queue:work
```

## Quick start

A working chat with Claude and a human fallback takes four steps.

**1. `.env`**

```dotenv
HYBRID_CHAT_AI_PROVIDERS=anthropic
ANTHROPIC_API_KEY=sk-ant-...
HYBRID_CHAT_NOTIFY=support@example.com   # emailed when a chat needs a person
```

**2. Who can use the inbox** (`app/Providers/AppServiceProvider.php`):

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewHybridChat', fn ($user) => $user->is_admin);
}
```

**3. The bubble** in your layout (`resources/views/layouts/app.blade.php`):

```blade
<body>
    {{ $slot }}

    <x-chat-hybrid-ai-bubble
        :name="auth()->user()?->name"
        :email="auth()->user()?->email"
        :identify="auth()->user()"
    />
</body>
```

`:identify` signs the logged-in user's id, so [AI tools](#let-the-ai-use-your-data) can safely look up that user's own records.

**4. Answer chats** at `https://your-app.com/chat-admin`.

## The chat bubble

### In Blade

```blade
<x-chat-hybrid-ai-bubble />
```

Every attribute is optional:

```blade
<x-chat-hybrid-ai-bubble
    :name="auth()->user()?->name"
    :email="auth()->user()?->email"
    :identify="auth()->user()"
    :meta="['plan' => 'pro', 'page_type' => 'pricing']"
    color="#0f766e"
    title="Ask Acme"
/>
```

`meta` is a flat list of string values. The AI agent receives it, and admins see it next to the conversation. The bubble also records the visitor's IP, user agent, and the page they started on.

Name, email and `meta` come from the browser, so a visitor can change them. Only `:identify` is trusted: it is signed with your app key and expires after `identity.ttl` (24 hours by default). See [visitor identity](#visitor-identity).

### On any other site

On static HTML, WordPress, Shopify, or anything else, paste one tag:

```html
<script src="https://your-app.com/api/chat/widget.js" defer></script>
```

With options:

```html
<script
    src="https://your-app.com/api/chat/widget.js"
    data-name="Vera"
    data-email="vera@example.com"
    data-color="#0f766e"
    data-title="Ask Acme"
    data-meta='{"plan":"pro","page_type":"pricing"}'
    defer
></script>
```

| Attribute | Purpose |
|---|---|
| `data-api` | API base URL. Defaults to the folder the script was loaded from (`…/api/chat`) |
| `data-name`, `data-email` | Prefill the visitor; with a name, the intro form is skipped |
| `data-color`, `data-title` | Override the configured colour and title |
| `data-meta` | JSON object of extra context |
| `data-identity` | a signed identity from `HybridChat::identity($user)` |

### Control it from JavaScript

```js
HybridChat.open();
HybridChat.close();
HybridChat.toggle();
```

```html
<button onclick="HybridChat.open()">Chat with support</button>
```

### Cross-domain embedding

When the bubble runs on a different domain from your Laravel app, the browser needs CORS. Laravel's default `config/cors.php` already covers `api/*`. If you narrowed `paths`, add the chat routes again:

```php
'paths' => ['api/chat/*', 'api/chat-admin/*'],
'allowed_headers' => ['*'], // must include X-Chat-Token and Authorization
```

### Texts and colour

```dotenv
HYBRID_CHAT_TITLE="Chat with us"
HYBRID_CHAT_WELCOME="Hi! How can we help you today?"
HYBRID_CHAT_COLOR="#4f46e5"
```

Position (`right` or `left`), required email, polling interval and maximum message length are set under `widget` in the config.

## The admin inbox

The inbox lives at `/chat-admin` and runs behind `web` + `auth` and the `viewHybridChat` gate. As with Horizon, the default gate only allows the `local` environment, so define your own:

```php
// Admin flag
Gate::define('viewHybridChat', fn ($user) => $user->is_admin);

// A fixed list
Gate::define('viewHybridChat', fn ($user) => in_array($user->email, ['ada@acme.com', 'bob@acme.com']));

// spatie/laravel-permission
Gate::define('viewHybridChat', fn ($user) => $user->hasRole('support'));
```

To move the inbox or use another guard, change it in `config/chat-hybrid-ai.php`:

```php
'admin' => [
    'prefix' => 'support/inbox',
    'middleware' => ['web', 'auth:staff'],
],
```

Admins can filter, search, reply, take over, hand back to the AI, close, reopen, email the transcript, and delete conversations (deletes are soft).

## AI agents

New conversations start with the AI when `HYBRID_CHAT_AI=true` (the default). Pick a driver with `HYBRID_CHAT_AI_DRIVER`.

### Laravel AI SDK (default)

The default `laravel-ai` driver runs on the [Laravel AI SDK](https://laravel.com/docs/ai-sdk). Providers and API keys are configured in `config/ai.php`. Pick the provider(s) for the chat:

```dotenv
# One provider
HYBRID_CHAT_AI_PROVIDERS=anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Several: tried in order when one is rate-limited, overloaded or out of credits
HYBRID_CHAT_AI_PROVIDERS=anthropic,openai,gemini
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=...
```

Leave `HYBRID_CHAT_AI_PROVIDERS` empty to use `ai.default`. Each provider uses its default model unless you choose one in `config/chat-hybrid-ai.php`:

```php
'drivers' => [
    'laravel-ai' => [
        'models' => [
            'anthropic' => 'claude-opus-5-5',
            'openai' => 'gpt-5-mini',
            'gemini' => 'gemini-3-flash',
        ],
        'max_tokens' => 4000,
        'max_steps' => 5,        // model round trips per reply, tool calls included
        'timeout' => 60,
        'handoff_tool' => true,  // the agent may call request_human
        'provider_options' => [
            'anthropic' => ['output_config' => ['effort' => 'low']],
        ],
    ],
],
```

`provider_options` is sent as-is to each provider's API.

| Provider | `.env` |
|---|---|
| Anthropic | `ANTHROPIC_API_KEY` |
| OpenAI | `OPENAI_API_KEY` |
| Gemini | `GEMINI_API_KEY` |
| Groq | `GROQ_API_KEY` |
| Mistral | `MISTRAL_API_KEY` |
| DeepSeek | `DEEPSEEK_API_KEY` |
| xAI | `XAI_API_KEY` |
| OpenRouter | `OPENROUTER_API_KEY` |
| Ollama (your own server) | `OLLAMA_URL=http://localhost:11434` |
| Any OpenAI-compatible API (LM Studio, vLLM, Together, …) | `OPENAI_COMPATIBLE_URL`, `OPENAI_COMPATIBLE_API_KEY`, provider name `openai-compatible` |
| Azure OpenAI, Amazon Bedrock, Cohere | see `config/ai.php` |

In tests, fake the agent with laravel/ai's fakes:

```php
use Siberfx\ChatHybridAi\Ai\SupportAgent;

SupportAgent::fake(['Yes, we ship to Turkey.']);
```

### Your own agent (webhook)

Use this when your agent lives in another service: Python, Node, n8n, a RAG pipeline, and so on.

```dotenv
HYBRID_CHAT_AI_DRIVER=webhook
HYBRID_CHAT_WEBHOOK_URL=https://agents.example.com/support
HYBRID_CHAT_WEBHOOK_SECRET=change-me
```

For every visitor message, the package POSTs:

```json
{
  "conversation": { "id": "9b1e…", "visitor_name": "Vera", "visitor_email": "vera@example.com", "visitor_id": "42", "subject": null, "meta": { "plan": "pro" } },
  "system_prompt": "You are a friendly customer support assistant…",
  "messages": [
    { "role": "user", "content": "Do you ship to Turkey?" }
  ]
}
```

The `X-Chat-Signature` header holds `hash_hmac('sha256', $rawBody, $secret)`. Your agent responds with:

```json
{ "reply": "Yes, in 3–5 days.", "handoff": false, "meta": {} }
```

Return `"reply": null` to stay silent, or `"handoff": true` to call a human. `visitor_id` is the verified id of a signed-in visitor (or `null`); it is the only visitor field your agent should trust.

A receiver in Laravel:

```php
Route::post('/support-agent', function (Request $request) {
    $expected = hash_hmac('sha256', $request->getContent(), config('services.chat_agent.secret'));
    abort_unless(hash_equals($expected, (string) $request->header('X-Chat-Signature')), 401);

    $question = collect($request->input('messages'))->last()['content'];

    $answer = KnowledgeBase::answer($question); // your own logic

    return $answer
        ? ['reply' => $answer]
        : ['reply' => 'Let me get a colleague for you.', 'handoff' => true];
});
```

The same receiver in Node (Express):

```js
import crypto from 'node:crypto';
import express from 'express';

const app = express();
app.use(express.raw({ type: 'application/json' }));

app.post('/support', (req, res) => {
    const expected = crypto.createHmac('sha256', process.env.SECRET).update(req.body).digest('hex');
    if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(req.get('X-Chat-Signature') ?? ''))) {
        return res.sendStatus(401);
    }

    const { messages } = JSON.parse(req.body);
    res.json({ reply: `You said: ${messages.at(-1).content}`, handoff: false });
});

app.listen(3000);
```

### A custom PHP driver

Implement `Siberfx\ChatHybridAi\Contracts\AiAgent`:

```php
namespace App\Chat;

use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Ai\ChatTurn;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

class FaqAgent implements AiAgent
{
    public function __construct(protected array $config) {}

    /** @param list<ChatTurn> $turns oldest first, ending with the visitor's message */
    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        $question = mb_strtolower(end($turns)->content);

        return match (true) {
            str_contains($question, 'opening hours') => new AiReply('We are open 9:00–18:00, Monday to Friday.'),
            str_contains($question, 'refund') => new AiReply('A colleague will help you with your refund.', handoff: true),
            default => null, // stay silent
        };
    }
}
```

Register it in `AppServiceProvider::boot()` and select it:

```php
use Siberfx\ChatHybridAi\Facades\HybridChat;

HybridChat::extendAi('faq', fn ($app, array $config) => new \App\Chat\FaqAgent($config));
```

```dotenv
HYBRID_CHAT_AI_DRIVER=faq
```

The driver's `$config` comes from `ai.drivers.faq` in the published config.

### System prompt, history and queue

```dotenv
HYBRID_CHAT_AI_PROMPT="You are Acme's support assistant. Only answer questions about Acme products. If you cannot help, say a colleague will take over and end with [[HANDOFF]]."
HYBRID_CHAT_AI_QUEUE=ai        # run AI replies on their own queue
```

`ai.history_limit` (default 30) sets how many recent messages the agent sees. When the visitor sends several messages in a row, a single reply answers all of them.

### Without AI

```dotenv
HYBRID_CHAT_AI=false
```

Every chat starts in `waiting`, and the package works as a regular human live chat.

## Let the AI use your data

The AI can answer questions from your database through **tools**: small, explicit functions you register, each doing one read. The AI decides when to call them; it never writes SQL or sees anything you didn't expose.

> "Do you have red running shoes under 100?" → `search_products`
> "Where is my order A-1042?" → `find_order`
> "Is the code WELCOME10 still valid?" → `check_coupon`

Tools need the `laravel-ai` driver. Register them in a service provider:

```php
// app/Providers/AppServiceProvider.php
use App\Models\Order;
use App\Models\Product;
use App\Models\Coupon;
use Siberfx\ChatHybridAi\Ai\ChatContext;
use Siberfx\ChatHybridAi\Ai\Tools\Check;
use Siberfx\ChatHybridAi\Ai\Tools\FindRecord;
use Siberfx\ChatHybridAi\Ai\Tools\SearchRecords;
use Siberfx\ChatHybridAi\Facades\HybridChat;

public function boot(): void
{
    HybridChat::tools([
        // Site search
        SearchRecords::model(Product::class)
            ->in(['name', 'description'])
            ->show(['name', 'price', 'url', 'in_stock'])
            ->where(fn ($query) => $query->where('published', true))
            ->limit(5),

        // The visitor's own order
        FindRecord::model(Order::class)
            ->by('number')
            ->ownedBy('user_id')          // signed-in visitors: only their own
            ->confirmWith('email')        // guests: must also give the order's email
            ->show(['number', 'status', 'shipped_at', 'tracking_url']),

        // Any check you can write in PHP
        Check::make(
            'check_coupon',
            'Check whether a discount code can be used right now.',
            ['code' => 'string'],
            fn (array $input) => Coupon::active()->where('code', $input['code'])->exists(),
        ),
    ]);
}
```

Every reply gets these tools. To vary them per chat, pass a closure: `HybridChat::tools(fn (ChatContext $context) => $context->isIdentified() ? [...] : [...])`.

### `SearchRecords`: search the site

| Method | |
|---|---|
| `model(Product::class)` | the model to search |
| `in([...])` | columns to search with `LIKE` (defaults to the `show` fields) |
| `show([...])` | **the only fields returned to the AI**; accessors work too |
| `where(fn ($query, ?ChatContext $context) => ...)` | limit the query; call it more than once to add more conditions |
| `limit(5)` | maximum results (1–50) |
| `usingScout()` | use Laravel Scout (`Product::search()`) instead of `LIKE` |
| `as('search_products')`, `describedAs('...')` | the tool name and description the AI sees (sensible defaults) |

### `FindRecord`: look up one record

| Method | |
|---|---|
| `by('number')` | the column the visitor gives (e.g. an order number) |
| `ownedBy('user_id')` | signed-in visitors only see records where this column is their id; guests are refused unless `confirmWith` is set |
| `confirmWith('email', 'postcode')` | the visitor must also give these values, matching the record (case-insensitive) |
| `show([...])`, `where(...)`, `as(...)`, `describedAs(...)` | as above |

Without `ownedBy` or `confirmWith`, it is a public lookup, fine for something like a product SKU. A failed ownership or confirmation check answers the same as "not found", so it never reveals that a record exists.

### `Check`: answers from your own code

```php
Check::make(
    'delivery_estimate',
    'Estimate delivery time to a postcode.',
    ['postcode' => 'string', 'express' => '?boolean'],   // types: string, integer, number, boolean; "?" = optional
    function (array $input, ?ChatContext $context) {
        $days = Shipping::daysTo($input['postcode'], express: $input['express'] ?? false);

        return $days === null
            ? ['deliverable' => false]
            : ['deliverable' => true, 'days' => $days];
    },
);
```

Return a bool, string, number or array. The visitor's verified id is `$context->visitorId()`, and their model is `$context->visitor()`.

### Your own tool class

Any [laravel/ai tool](https://laravel.com/docs/ai-sdk#tools) works. Use `ChatContext::current()` to know who you are talking to:

```php
namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Siberfx\ChatHybridAi\Ai\ChatContext;

class MySubscription implements Tool
{
    public function description(): string
    {
        return "Get the signed-in visitor's subscription plan and renewal date.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $user = ChatContext::current()?->visitor();

        if (! $user) {
            return 'The visitor is not signed in. Ask them to sign in first.';
        }

        return json_encode([
            'plan' => $user->subscription?->plan,
            'renews_at' => $user->subscription?->renews_at?->toDateString(),
        ]);
    }
}
```

Register tool classes by name in the config (they are resolved from the container), or as instances with `HybridChat::tools([...])`:

```php
'ai' => [
    'tools' => [
        App\Ai\Tools\MySubscription::class,
    ],
],
```

laravel/ai's ready-made tools work too:

```php
use App\Models\HelpArticle;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Tools\SimilaritySearch;

HybridChat::tools([
    new WebSearch,                                                      // provider-side web search
    SimilaritySearch::usingModel(HelpArticle::class, 'embedding'),     // answer from your docs (PostgreSQL + pgvector)
]);
```

### Visitor identity

A tool must know **who** it is talking to before it shows private data. The name, email and `meta` a visitor sends can't be trusted, because anyone can type `data-meta='{"user_id":"5"}'`. So the page that renders the chat signs the logged-in user's id with your app key:

```blade
<x-chat-hybrid-ai-bubble :identify="auth()->user()" />
```

```php
// For your own widget or SPA, e.g. as an Inertia shared prop
'chatIdentity' => fn () => $request->user() ? HybridChat::identity($request->user()) : null,
```

The API checks the signature and stores the id on the conversation as `visitor_id`. Forged or expired identities are ignored, so the visitor simply stays a guest. A guest who signs in mid-chat can send the identity with `PATCH conversations/{id}`. Once a chat has an identity, it can't be switched to another user.

`$context->visitor()` loads the user with `models.visitor`, which defaults to your auth user model.

### What admins see

Every lookup is saved with the AI's reply. The inbox shows it under the message, e.g. `🔎 find_order(number: "A-1042")`, so you can always check where an answer came from. The admin API returns it as `tools` on each message. Visitors never see it.

### Ground rules

- Tools only read. Make sure your closures do too.
- `show()` is an allowlist. Never return whole models: hidden attributes, costs and internal notes stay out.
- Tool results are given to the AI as data, and its instructions tell it not to follow instructions found inside them.
- `max_steps` (default 5) limits how many tool round trips one reply can take.

## Handoff to a human

A conversation is in one of three modes:

| mode | who answers | how it gets there |
|---|---|---|
| `ai` | the AI agent | new chats (when AI is on); an admin clicks **Hand back to AI** |
| `waiting` | nobody yet: it appears under **Needs a human** | the visitor clicks **Talk to a person**; the AI calls the `request_human` tool, ends its reply with `[[HANDOFF]]`, or (webhook) returns `handoff: true`; the model refuses; or the AI errors |
| `human` | an admin | an admin clicks **Take over** or simply replies |

When a chat enters `waiting`:

- a system message tells the visitor that a person will join,
- `HandoffRequested` fires with a reason: `visitor`, `ai`, `refusal`, or `ai_error`,
- the addresses in `HYBRID_CHAT_NOTIFY` get an email:

```dotenv
HYBRID_CHAT_NOTIFY=support@example.com,lead@example.com
```

## Email transcripts

- **Visitor:** menu → **Email me this chat**. The mail only goes to the address on the conversation, so the endpoint can't be used to send mail anywhere else. It is limited to 3 per 10 minutes per conversation.
- **Admin:** **Email transcript** → any addresses (up to 10).
- **Automatically on close:**

```dotenv
HYBRID_CHAT_TRANSCRIPT_VISITOR=true          # send to the visitor
HYBRID_CHAT_TRANSCRIPT_TO=archive@example.com # and/or to a list
```

Mails are queued by default (`transcripts.queue`). To restyle them, publish the views (`--tag=chat-hybrid-ai-views`) and edit `resources/views/vendor/chat-hybrid-ai/mail/*`.

## REST API: visitor

Base URL: `/api/chat`. The API is public and rate-limited (60 requests per minute, plus 10 new conversations per minute per IP). Starting a conversation returns a **token**, and every later call sends it in the `X-Chat-Token` header. A wrong or missing token returns `404`.

| Method | Path | Body / query | Returns |
|---|---|---|---|
| GET | `widget.js` | | the bubble script |
| GET | `config` | | widget settings |
| POST | `conversations` | `name?`, `email?`, `subject?`, `message?`, `meta?`, `identity?` | `201` `{token, conversation, messages}` |
| GET | `conversations/{id}` | | `{conversation, messages}` |
| PATCH | `conversations/{id}` | `name?`, `email?`, `identity?` | `{data: conversation}` |
| GET | `conversations/{id}/messages` | `?after={lastMessageId}` | `{conversation, messages}` (up to 100) |
| POST | `conversations/{id}/messages` | `body` | `201` `{data: message}` |
| POST | `conversations/{id}/read` | | `204` |
| POST | `conversations/{id}/human` | | `{data: conversation}` |
| POST | `conversations/{id}/close` | | `{data: conversation}` |
| POST | `conversations/{id}/transcript` | `email?` | `{sent: true, email}` |

### A full conversation with curl

```bash
API=https://your-app.com/api/chat

# Settings for your UI
curl -s $API/config
# {"title":"Chat with us","welcome":"Hi! How can we help you today?","color":"#4f46e5","position":"right",
#  "require_email":false,"poll_interval":3000,"max_message_length":4000,"ai":true}

# Start, with the first message
curl -s -X POST $API/conversations \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Vera","email":"vera@example.com","message":"Do you ship to Turkey?","meta":{"plan":"pro"}}'
```

```json
{
  "token": "Qm9…64 chars",
  "conversation": {
    "id": "9b1e6c2a-…",
    "status": "open",
    "mode": "ai",
    "visitor_name": "Vera",
    "visitor_email": "vera@example.com",
    "identified": false,
    "created_at": "2026-10-19T12:00:00+00:00"
  },
  "messages": [
    { "id": 1, "sender_type": "visitor", "sender_name": "Vera", "body": "Do you ship to Turkey?", "event": null, "created_at": "2026-10-19T12:00:00+00:00" }
  ]
}
```

```bash
ID=9b1e6c2a-…; TOKEN=Qm9…
H=(-H "Accept: application/json" -H "Content-Type: application/json" -H "X-Chat-Token: $TOKEN")

# Poll for the AI's answer (send the last id you have)
curl -s "${H[@]}" "$API/conversations/$ID/messages?after=1"
# {"conversation":{…},"messages":[{"id":2,"sender_type":"ai","sender_name":"Assistant","body":"Yes, in 3–5 days.",…}]}

# Reply
curl -s "${H[@]}" -X POST $API/conversations/$ID/messages -d '{"body":"Great, and the cost?"}'

# Ask for a person: mode becomes "waiting"
curl -s "${H[@]}" -X POST $API/conversations/$ID/human

# Email the transcript, then close
curl -s "${H[@]}" -X POST $API/conversations/$ID/transcript -d '{"email":"vera@example.com"}'
curl -s "${H[@]}" -X POST $API/conversations/$ID/close
```

### Shapes

**Message**

| field | |
|---|---|
| `id` | integer; increases, so use it for `?after=` |
| `sender_type` | `visitor`, `ai`, `agent`, or `system` |
| `sender_name` | display name |
| `body` | plain text; render it as text, never as HTML |
| `event` | for system messages: `handoff`, `joined`, `ai`, or `closed` |
| `created_at` | ISO 8601 |

**Conversation**: `id` (uuid), `status` (`open` or `closed`), `mode` (`ai`, `waiting`, or `human`), `visitor_name`, `visitor_email`, `identified` (true when a signed identity was accepted), `created_at`.

**Errors**: `404` (wrong token or unknown conversation), `422` (`{message, errors}` from validation), `429` (rate limited).

## REST API: admin

The admin API is served twice, with the same endpoints:

| | Base URL | Auth | For |
|---|---|---|---|
| Session | `/chat-admin/api` | `web` + `auth` (cookie) | the bundled inbox; a same-site SPA |
| Token | `/api/chat-admin` | `api` + `auth:sanctum` (bearer) | external SPAs, mobile apps, scripts |

Both check the `viewHybridChat` gate. The token API is on when Sanctum is installed (`php artisan install:api`). Force it on or off with `HYBRID_CHAT_ADMIN_API=true|false`, and change its prefix or guard in `routes.admin_api`.

| Method | Path | Body / query |
|---|---|---|
| GET | `config` | → `{poll_interval, ai}` |
| GET | `stats` | → counts: `open`, `ai`, `waiting`, `human`, `unread` |
| GET | `conversations` | `?status=open\|closed&mode=ai\|waiting\|human&mine=1&unread=1&search=&per_page=&page=` (paginated) |
| GET | `conversations/{id}` | conversation + all messages; marks it read |
| GET | `conversations/{id}/messages` | `?after=` |
| POST | `conversations/{id}/messages` | `body`; takes the chat over from the AI |
| POST | `conversations/{id}/take-over` | |
| POST | `conversations/{id}/return-to-ai` | |
| POST | `conversations/{id}/close` | |
| POST | `conversations/{id}/reopen` | |
| POST | `conversations/{id}/transcript` | `emails[]?` (defaults to the visitor) |
| DELETE | `conversations/{id}` | soft delete, `204` |

An admin conversation also includes `visitor_id` (the verified user id, or `null`), `subject`, `meta`, `assignee` (`{id, name}`), `latest_message`, `unread`, `last_message_at`, and `closed_at`. Admin messages also include `tools`: the lookups the AI made for that reply, as `[{name, arguments}]`.

### Tokens for admins

Your user model needs Sanctum's `HasApiTokens` trait. Issue a token, for example from a login endpoint in your mobile app:

```php
Route::post('/api/support/login', function (Request $request) {
    $user = User::where('email', $request->email)->first();

    abort_unless($user && Hash::check($request->password, $user->password), 401);
    abort_unless(Gate::forUser($user)->allows('viewHybridChat'), 403);

    return ['token' => $user->createToken('chat-admin')->plainTextToken];
});
```

Then:

```bash
TOKEN=1|abc…
A=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN")

curl -s "${A[@]}" https://your-app.com/api/chat-admin/stats
# {"open":12,"ai":7,"waiting":3,"human":2,"unread":4}

curl -s "${A[@]}" "https://your-app.com/api/chat-admin/conversations?mode=waiting&per_page=20"

curl -s "${A[@]}" -H "Content-Type: application/json" -X POST \
  https://your-app.com/api/chat-admin/conversations/$ID/messages -d '{"body":"Hi Vera, Ada here."}'

curl -s "${A[@]}" -X POST https://your-app.com/api/chat-admin/conversations/$ID/return-to-ai
```

## JavaScript client

`resources/js/client/hybrid-chat.js` has no dependencies and works in any framework or plain JS. Copy it into your app with:

```bash
php artisan vendor:publish --tag=chat-hybrid-ai-js
```

This publishes into `resources/js/vendor/chat-hybrid-ai/`:

| File | What it is |
|---|---|
| `client/hybrid-chat.js` | `ChatClient`, `ChatSession`, `AdminClient`, `ApiError` |
| `client/chat-widget.css` | styles shared by both widgets; set `--hc-color` to restyle |
| `react/useHybridChat.js`, `react/ChatWidget.jsx` | React 18+ hook and widget |
| `vue/useHybridChat.js`, `vue/ChatWidget.vue` | Vue 3 composable and widget |

### `ChatClient`: the raw visitor API

```js
import { ChatClient } from './vendor/chat-hybrid-ai/client/hybrid-chat.js';

const chat = new ChatClient({ api: 'https://your-app.com/api/chat' });

const { token, conversation } = await chat.start({ name: 'Vera', message: 'Hi!' });
await chat.send('Do you ship to Turkey?');
const { messages } = await chat.messages(0);      // everything after id 0
await chat.requestHuman();
await chat.transcript('vera@example.com');
await chat.close();

// Resume later with the saved id + token
const again = new ChatClient({ api: '/api/chat', id: conversation.id, token });
```

Methods: `config()`, `start(data)`, `show()`, `update({name, email})`, `messages(after)`, `send(body)`, `markRead()`, `requestHuman()`, `close()`, `transcript(email?)`.

### `ChatSession`: a full chat, ready to bind to a UI

`ChatSession` does what the bubble does: it starts a conversation on the first message, saves the token in `localStorage`, resumes after a reload, polls (faster while open, slower in a background tab), counts unread replies, and shows a "typing" state while the AI works.

```js
import { ChatSession } from './vendor/chat-hybrid-ai/client/hybrid-chat.js';

const session = new ChatSession({ api: '/api/chat', name: 'Vera', meta: { plan: 'pro' }, identity: window.chatIdentity });

session.subscribe((state) => render(state));
await session.start();

session.setOpen(true);
await session.send('Hello!');
```

The state object holds `ready`, `config`, `conversation`, `messages`, `open`, `unread`, `sending`, `awaitingReply`, and `error`. Each change replaces the object instead of mutating it. The actions are `start()`, `stop()`, `setOpen(bool)`, `send(body, {name, email})` (resolves to `true` or `false`), `requestHuman()`, `emailTranscript(email)`, and `end()`.

## React

### Drop-in widget

```jsx
// resources/js/app.jsx
import ChatWidget from './vendor/chat-hybrid-ai/react/ChatWidget.jsx';

export default function Layout({ user, chatIdentity, children }) {
    return (
        <>
            {children}
            <ChatWidget
                api="/api/chat"
                name={user?.name}
                email={user?.email}
                identity={chatIdentity}
                meta={{ plan: user?.plan ?? 'free' }}
            />
        </>
    );
}
```

`chatIdentity` comes from `HybridChat::identity($user)`; see [visitor identity](#visitor-identity). With Inertia, share it as a prop and put the widget in your persistent layout so the chat survives page visits. In Next.js or another separate front end, use the full URL (`api="https://your-app.com/api/chat"`), mark the file `'use client'`, and allow CORS.

### Your own UI with the hook

```jsx
import { useState } from 'react';
import { useHybridChat } from './vendor/chat-hybrid-ai/react/useHybridChat.js';

export default function SupportPage() {
    const chat = useHybridChat({ api: '/api/chat', name: 'Vera' });
    const [text, setText] = useState('');

    if (!chat.ready) return <p>Loading…</p>;

    async function submit(e) {
        e.preventDefault();
        if (await chat.send(text)) setText('');
    }

    return (
        <section>
            <h2>{chat.config.title}</h2>

            <ul>
                {chat.messages.map((m) => (
                    <li key={m.id} style={{ opacity: m.pending ? 0.5 : 1 }}>
                        <strong>{m.sender_name ?? 'You'}:</strong> {m.body}
                    </li>
                ))}
            </ul>

            {chat.awaitingReply && <p>The assistant is typing…</p>}
            {chat.error && <p role="alert">{chat.error}</p>}

            <form onSubmit={submit}>
                <input value={text} onChange={(e) => setText(e.target.value)} placeholder="Message" />
                <button disabled={chat.sending}>Send</button>
            </form>

            {chat.conversation?.mode === 'ai' && <button onClick={chat.requestHuman}>Talk to a person</button>}
            {chat.conversation && <button onClick={chat.end}>End chat</button>}
        </section>
    );
}
```

The hook returns the `ChatSession` state plus `send`, `setOpen`, `requestHuman`, `emailTranscript`, `end`, and the underlying `session`.

## Vue

### Drop-in widget

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<script setup>
import ChatWidget from '@/vendor/chat-hybrid-ai/vue/ChatWidget.vue';

defineProps({ user: Object, chatIdentity: String });
</script>

<template>
    <slot />
    <ChatWidget api="/api/chat" :name="user?.name" :email="user?.email" :identity="chatIdentity" :meta="{ plan: user?.plan ?? 'free' }" />
</template>
```

In Nuxt, wrap it in `<ClientOnly>` and use the full API URL.

### Your own UI with the composable

```vue
<script setup>
import { ref } from 'vue';
import { useHybridChat } from '@/vendor/chat-hybrid-ai/vue/useHybridChat.js';

const { state, send, requestHuman, end } = useHybridChat({ api: '/api/chat', name: 'Vera' });
const text = ref('');

async function submit() {
    if (await send(text.value)) text.value = '';
}
</script>

<template>
    <section v-if="state.ready">
        <h2>{{ state.config.title }}</h2>

        <ul>
            <li v-for="m in state.messages" :key="m.id" :style="{ opacity: m.pending ? 0.5 : 1 }">
                <strong>{{ m.sender_name ?? 'You' }}:</strong> {{ m.body }}
            </li>
        </ul>

        <p v-if="state.awaitingReply">The assistant is typing…</p>
        <p v-if="state.error" role="alert">{{ state.error }}</p>

        <form @submit.prevent="submit">
            <input v-model="text" placeholder="Message">
            <button :disabled="state.sending">Send</button>
        </form>

        <button v-if="state.conversation?.mode === 'ai'" @click="requestHuman">Talk to a person</button>
        <button v-if="state.conversation" @click="end">End chat</button>
    </section>
</template>
```

`state` is a `shallowRef` holding the `ChatSession` state. In `<script>`, read it as `state.value.messages`; in the template, as `state.messages`.

## Build your own admin app

`AdminClient` wraps the admin API:

```js
import { AdminClient } from './vendor/chat-hybrid-ai/client/hybrid-chat.js';

// Token (mobile app, external SPA)
const admin = new AdminClient({ api: 'https://your-app.com/api/chat-admin', token: sanctumToken });

// Or the session, in a same-site SPA
const admin = new AdminClient({
    api: '/chat-admin/api',
    credentials: 'include',
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
});
```

Methods: `config()`, `stats()`, `conversations(filters)`, `show(id)`, `messages(id, after)`, `send(id, body)`, `takeOver(id)`, `returnToAi(id)`, `close(id)`, `reopen(id)`, `transcript(id, emails?)`, `destroy(id)`. Failed requests throw an `ApiError` with `status` and `body`.

### A small React inbox

```jsx
import { useEffect, useState } from 'react';
import { AdminClient } from './vendor/chat-hybrid-ai/client/hybrid-chat.js';

const admin = new AdminClient({ token: localStorage.getItem('chat-admin-token') });

export default function Inbox() {
    const [list, setList] = useState([]);
    const [active, setActive] = useState(null);
    const [messages, setMessages] = useState([]);
    const [reply, setReply] = useState('');

    // Refresh the "needs a human" list every 5 s
    useEffect(() => {
        const load = () => admin.conversations({ mode: 'waiting' }).then((r) => setList(r.data));
        load();
        const timer = setInterval(load, 5000);
        return () => clearInterval(timer);
    }, []);

    // Load and poll the open conversation
    useEffect(() => {
        if (!active) return;
        let lastId = 0;
        admin.show(active).then((r) => { setMessages(r.messages); lastId = r.messages.at(-1)?.id ?? 0; });
        const timer = setInterval(async () => {
            const r = await admin.messages(active, lastId);
            if (r.messages.length) {
                lastId = r.messages.at(-1).id;
                setMessages((prev) => [...prev, ...r.messages]);
            }
        }, 3000);
        return () => clearInterval(timer);
    }, [active]);

    async function send(e) {
        e.preventDefault();
        const { data } = await admin.send(active, reply);
        setMessages((prev) => [...prev, data]);
        setReply('');
    }

    return (
        <div style={{ display: 'flex', gap: 24 }}>
            <ul>
                {list.map((c) => (
                    <li key={c.id}>
                        <button onClick={() => setActive(c.id)}>
                            {c.visitor_name ?? 'Visitor'} · {c.latest_message?.body}
                        </button>
                    </li>
                ))}
            </ul>

            {active && (
                <div>
                    {messages.map((m) => <p key={m.id}><b>{m.sender_name}:</b> {m.body}</p>)}
                    <form onSubmit={send}>
                        <input value={reply} onChange={(e) => setReply(e.target.value)} />
                        <button>Reply</button>
                    </form>
                    <button onClick={() => admin.returnToAi(active)}>Hand back to AI</button>
                    <button onClick={() => admin.close(active).then(() => setActive(null))}>Close</button>
                </div>
            )}
        </div>
    );
}
```

### The same in Vue

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AdminClient } from '@/vendor/chat-hybrid-ai/client/hybrid-chat.js';

const admin = new AdminClient({ token: localStorage.getItem('chat-admin-token') });
const list = ref([]);
const active = ref(null);
const messages = ref([]);
const reply = ref('');
let listTimer, chatTimer, lastId = 0;

const loadList = async () => { list.value = (await admin.conversations({ mode: 'waiting' })).data; };

onMounted(() => { loadList(); listTimer = setInterval(loadList, 5000); });
onBeforeUnmount(() => { clearInterval(listTimer); clearInterval(chatTimer); });

watch(active, async (id) => {
    clearInterval(chatTimer);
    if (!id) return;
    const r = await admin.show(id);
    messages.value = r.messages;
    lastId = r.messages.at(-1)?.id ?? 0;
    chatTimer = setInterval(async () => {
        const { messages: fresh } = await admin.messages(id, lastId);
        if (fresh.length) { lastId = fresh.at(-1).id; messages.value.push(...fresh); }
    }, 3000);
});

async function send() {
    const { data } = await admin.send(active.value, reply.value);
    messages.value.push(data);
    reply.value = '';
}
</script>

<template>
    <div style="display: flex; gap: 24px">
        <ul>
            <li v-for="c in list" :key="c.id">
                <button @click="active = c.id">{{ c.visitor_name ?? 'Visitor' }} · {{ c.latest_message?.body }}</button>
            </li>
        </ul>

        <div v-if="active">
            <p v-for="m in messages" :key="m.id"><b>{{ m.sender_name }}:</b> {{ m.body }}</p>
            <form @submit.prevent="send">
                <input v-model="reply">
                <button>Reply</button>
            </form>
            <button @click="admin.returnToAi(active)">Hand back to AI</button>
            <button @click="admin.close(active).then(() => (active = null))">Close</button>
        </div>
    </div>
</template>
```

## Real-time with Reverb or Pusher

The bubble and the inbox poll every `widget.poll_interval` ms (3 s by default; slower when the chat is closed or the tab is in the background). With Reverb or Pusher set up, also broadcast new messages:

```dotenv
HYBRID_CHAT_BROADCAST=true
```

Every new message is then sent as `message.created`, with the payload `{ conversation: uuid, message: Message }`, on:

- `chat.conversation.{uuid}`: a public channel for the visitor (the uuid can't be guessed)
- `chat.admin`: a private channel, authorised by the `viewHybridChat` gate

Listen with Laravel Echo. The leading dot is needed because the event uses a custom name:

```js
// Visitor
Echo.channel(`chat.conversation.${conversationId}`)
    .listen('.message.created', ({ message }) => addMessage(message));

// Admin
Echo.private('chat.admin')
    .listen('.message.created', ({ conversation, message }) => refreshInbox(conversation, message));
```

Keep polling as a fallback, or raise `poll_interval` once Echo is connected.

## Using it from PHP

Every state change goes through `ChatManager` (facade `HybridChat`), so your code behaves exactly like the HTTP API: events, AI jobs and emails included.

```php
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Models\Conversation;

// Start a chat for a logged-in customer, e.g. from an order page
$chat = HybridChat::start([
    'visitor_name' => $user->name,
    'visitor_email' => $user->email,
    'visitor_id' => $user->id,     // trusted: you set it on the server
    'subject' => "Order #{$order->number}",
    'meta' => ['order' => (string) $order->number, 'total' => (string) $order->total],
], 'I have a question about my order.');

// Messages
HybridChat::visitorMessage($chat, 'Where is it?');          // the AI answers (queued)
HybridChat::agentMessage($chat, $admin, 'Shipped today!');  // takes over from the AI
HybridChat::aiMessage($chat, 'Anything else?');
HybridChat::systemMessage($chat, 'Order status changed to shipped.');

// Modes
HybridChat::requestHuman($chat);           // reason "visitor"
HybridChat::takeOver($chat, $admin);
HybridChat::returnToAi($chat);

// Lifecycle
HybridChat::close($chat);
HybridChat::reopen($chat);
HybridChat::sendTranscript($chat, ['someone@example.com']);

HybridChat::aiEnabled();                 // bool
HybridChat::identity($user);             // signed identity for a widget
HybridChat::tools([...]);                // give the AI tools
```

Query with the models and their scopes:

```php
use Siberfx\ChatHybridAi\Enums\ConversationMode;

Conversation::open()->mode(ConversationMode::Waiting)->count();
Conversation::open()->unreadByAdmin()->with('latestMessage')->get();
Conversation::where('visitor_email', 'vera@example.com')->latest('last_message_at')->first();

$chat->messages()->orderBy('id')->get();
$chat->assignee;                 // the admin user, or null
$chat->unreadForAdminCount();
```

## Events

| Event | Properties | When |
|---|---|---|
| `ConversationStarted` | `$conversation` | a chat is created |
| `MessageCreated` | `$message` (`$message->conversation` is loaded) | any message, from anyone |
| `HandoffRequested` | `$conversation`, `$reason` (`visitor`, `ai`, `refusal`, `ai_error`) | a chat needs a person |
| `ConversationClosed` | `$conversation` | a chat is closed |

All of them live in `Siberfx\ChatHybridAi\Events`. Example: post to Slack when a chat needs a person.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Siberfx\ChatHybridAi\Events\HandoffRequested;

Event::listen(function (HandoffRequested $event) {
    $c = $event->conversation;

    Http::post(config('services.slack.support_webhook'), [
        'text' => "💬 {$c->visitor_name} ({$c->visitor_email}) needs a person ({$event->reason}): ".url('/chat-admin'),
    ]);
});
```

Or keep your CRM in sync:

```php
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Events\MessageCreated;

Event::listen(function (MessageCreated $event) {
    if ($event->message->sender_type === SenderType::Visitor && $email = $event->message->conversation->visitor_email) {
        Crm::logActivity($email, $event->message->body);
    }
});
```

## Custom models and tables

Extend the package models and point the config at your classes:

```php
namespace App\Models;

use Siberfx\ChatHybridAi\Models\Conversation as BaseConversation;

class ChatConversation extends BaseConversation
{
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'visitor_email', 'email');
    }
}
```

```php
// config/chat-hybrid-ai.php
'models' => [
    'conversation' => App\Models\ChatConversation::class,
    'message' => Siberfx\ChatHybridAi\Models\Message::class,
    'agent' => App\Models\Staff::class,     // admins; defaults to auth.providers.users.model
    'visitor' => App\Models\Customer::class, // signed-in visitors; defaults to auth.providers.users.model
],

'tables' => [
    'conversations' => 'support_conversations',
    'messages' => 'support_messages',
],
```

Change table names before you run the migration.

## Configuration reference

All settings are in [`config/chat-hybrid-ai.php`](config/chat-hybrid-ai.php). These ones can be set from `.env`:

| Variable | Default | |
|---|---|---|
| `HYBRID_CHAT_TITLE` | `Chat with us` | bubble title |
| `HYBRID_CHAT_WELCOME` | `Hi! How can we help you today?` | first message shown |
| `HYBRID_CHAT_COLOR` | `#4f46e5` | brand colour |
| `HYBRID_CHAT_AI` | `true` | AI answers first |
| `HYBRID_CHAT_AI_DRIVER` | `laravel-ai` | `laravel-ai`, `webhook`, `null`, or a custom name |
| `HYBRID_CHAT_AI_PROVIDERS` | `ai.default` | comma-separated laravel/ai providers, in failover order |
| `HYBRID_CHAT_AI_QUEUE` | default queue | queue for AI replies |
| `HYBRID_CHAT_AI_PROMPT` | built-in support prompt | system prompt |
| `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, … | | provider keys, read by `config/ai.php` |
| `HYBRID_CHAT_WEBHOOK_URL` | | your agent |
| `HYBRID_CHAT_WEBHOOK_SECRET` | | HMAC secret |
| `HYBRID_CHAT_ADMIN_API` | on if Sanctum is installed | token admin API |
| `HYBRID_CHAT_NOTIFY` | | comma-separated emails for handoffs |
| `HYBRID_CHAT_TRANSCRIPT_VISITOR` | `false` | email the visitor on close |
| `HYBRID_CHAT_TRANSCRIPT_TO` | | comma-separated emails on close |
| `HYBRID_CHAT_BROADCAST` | `false` | broadcast `MessageCreated` |

Config-only settings: `routes.*` (prefixes, middleware, `enabled`), `widget.position`, `widget.require_email`, `widget.poll_interval`, `widget.max_message_length`, `ai.history_limit`, `ai.handoff_marker`, `ai.tools`, `ai.drivers.laravel-ai.*` (`models`, `max_tokens`, `max_steps`, `timeout`, `handoff_tool`, `provider_options`), `ai.drivers.webhook.timeout`, `identity.ttl`, `transcripts.queue`, `models.*`, and `tables.*`.

To use only the PHP API and register your own routes, set `routes.enabled` to `false`.

## Security

- **Visitor access**: each conversation has a random 64-character token. Without it, every endpoint returns `404`, and the token never appears in admin responses.
- **Rate limits**: 60 requests per minute on the widget API, 10 new conversations per minute per IP, and 3 visitor transcript emails per 10 minutes per conversation.
- **Transcripts**: visitors can only email their own address, so the API can't be used to send mail to other people.
- **Admin**: every admin request, on the session or with a token, goes through your auth middleware and the `viewHybridChat` gate.
- **Webhook**: verify `X-Chat-Signature` with `hash_equals` (see the examples above).
- **AI tools**: read-only, allowlisted fields, scoped to the signed-in visitor or confirmed by a detail only the owner knows. The visitor's name, email and `meta` are never used to decide whose data to show.
- **Visitor identity**: HMAC-signed with your app key, expiring (`identity.ttl`), and fixed to the conversation once accepted.
- **Rendering**: message bodies are plain text. The bundled UIs render them as text; do the same in your own UI.

## Upgrading from 1.x

1. `composer require siberfx/chat-hybrid-ai:^2.0`, then `php artisan migrate` (adds `visitor_id` to conversations).
2. The `anthropic` and `openai` drivers are gone; the `laravel-ai` driver replaces both and is the new default. Move your settings:

| 1.x `.env` | 2.x |
|---|---|
| `HYBRID_CHAT_AI_DRIVER=anthropic` | `HYBRID_CHAT_AI_PROVIDERS=anthropic` (and remove the driver line) |
| `HYBRID_CHAT_AI_DRIVER=openai` | `HYBRID_CHAT_AI_PROVIDERS=openai` (or `openrouter`, `groq`, `ollama`, `openai-compatible`, …) |
| `ANTHROPIC_API_KEY` | unchanged |
| `HYBRID_CHAT_ANTHROPIC_MODEL` | `ai.drivers.laravel-ai.models.anthropic` in the config |
| `HYBRID_CHAT_OPENAI_KEY` / `_URL` / `_MODEL` | `OPENAI_API_KEY` / `OPENAI_URL` / `ai.drivers.laravel-ai.models.openai` |

3. If you published `config/chat-hybrid-ai.php`, copy the new `ai.tools`, `ai.drivers.laravel-ai`, `identity` and `models.visitor` keys from the package config.
4. The Anthropic server-side refusal fallback (`fallbacks`) is gone. Refusals still hand the chat to a human, and you can fail over to other providers with `HYBRID_CHAT_AI_PROVIDERS`.

Webhook and custom drivers, the API, the widgets and the events are unchanged.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
