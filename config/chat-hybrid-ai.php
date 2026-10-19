<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models & tables
    |--------------------------------------------------------------------------
    | Swap in your own models (they should extend the package models) and
    | table names. The agent model is the user model of your admins; the
    | visitor model is the user model of signed-in visitors.
    */
    'models' => [
        'conversation' => Siberfx\ChatHybridAi\Models\Conversation::class,
        'message' => Siberfx\ChatHybridAi\Models\Message::class,
        'agent' => null, // defaults to auth.providers.users.model
        'visitor' => null, // defaults to auth.providers.users.model
    ],

    'tables' => [
        'conversations' => 'chat_conversations',
        'messages' => 'chat_messages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | "widget" is the public API used by the chat bubble. Visitors are
    | identified by a per-conversation token sent as the X-Chat-Token header.
    | "admin" is the inbox page and the session-authenticated API it uses.
    | "admin_api" serves the same admin API to token clients (SPAs, mobile
    | apps): on by default when Laravel Sanctum is installed. The
    | "viewHybridChat" gate is checked on every admin request.
    */
    'routes' => [
        'enabled' => true,

        'widget' => [
            'prefix' => 'api/chat',
            'middleware' => ['throttle:60,1'],
        ],

        'admin' => [
            'prefix' => 'chat-admin',
            'middleware' => ['web', 'auth'],
        ],

        'admin_api' => [
            'enabled' => env('HYBRID_CHAT_ADMIN_API', class_exists(Laravel\Sanctum\Sanctum::class)),
            'prefix' => 'api/chat-admin',
            'middleware' => ['api', 'auth:sanctum'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    */
    'widget' => [
        'title' => env('HYBRID_CHAT_TITLE', 'Chat with us'),
        'welcome' => env('HYBRID_CHAT_WELCOME', 'Hi! How can we help you today?'),
        'color' => env('HYBRID_CHAT_COLOR', '#4f46e5'),
        'position' => 'right', // right | left
        'require_email' => false,
        'poll_interval' => 3000, // ms
        'max_message_length' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI agent
    |--------------------------------------------------------------------------
    | New conversations start in "ai" mode when enabled. The agent can hand the
    | conversation to a human by answering with the handoff marker, the visitor
    | can ask for a human, and admins can take over at any time.
    |
    | Drivers: laravel-ai (the Laravel AI SDK: Anthropic, OpenAI, Gemini,
    | Groq, Mistral, Ollama, OpenRouter, ...), webhook (your own agent
    | service), null. Or register your own with
    | HybridChat::extendAi('name', fn ($app, $config) => new MyDriver).
    */
    'ai' => [
        'enabled' => env('HYBRID_CHAT_AI', true),
        'driver' => env('HYBRID_CHAT_AI_DRIVER', 'laravel-ai'),
        'queue' => env('HYBRID_CHAT_AI_QUEUE'), // null = default queue
        'history_limit' => 30,
        'handoff_marker' => '[[HANDOFF]]',

        'system_prompt' => env('HYBRID_CHAT_AI_PROMPT',
            "You are a friendly customer support assistant for this website. Answer briefly and clearly. "
            ."If you cannot help, the visitor asks for a human, or the request involves billing disputes, "
            ."account access, or anything you are unsure about, reply with a short message saying a team member "
            ."will take over and end your reply with [[HANDOFF]]."
        ),

        /*
        | Tools let the agent read your data: search products, look up the
        | visitor's own order, check a coupon. Class names here are resolved
        | from the container; register configured instances with
        | HybridChat::tools([...]) in a service provider. They work with the
        | "laravel-ai" driver. Any laravel/ai Tool or provider tool fits.
        */
        'tools' => [
            // App\Ai\Tools\OrderStatus::class,
        ],

        'drivers' => [
            /*
            | The Laravel AI SDK: providers and keys live in config/ai.php.
            | List several providers to fail over between them, in order
            | (on rate limits, overloads, or exhausted credits).
            */
            'laravel-ai' => [
                'providers' => array_values(array_filter(explode(',', (string) env('HYBRID_CHAT_AI_PROVIDERS', '')))), // empty = ai.default
                'models' => [
                    // 'anthropic' => 'claude-opus-5-5',
                    // 'openai' => 'gpt-5-mini',
                ], // per provider; unset = the provider's default model
                'max_tokens' => 4000,
                'max_steps' => 5, // model round trips per reply, tool calls included
                'timeout' => 60,
                'handoff_tool' => true, // the agent can call request_human
                'provider_options' => [
                    'anthropic' => ['output_config' => ['effort' => 'low']], // chat rarely needs deep thinking
                ],
            ],

            'webhook' => [
                'url' => env('HYBRID_CHAT_WEBHOOK_URL'),
                'secret' => env('HYBRID_CHAT_WEBHOOK_SECRET'),
                'timeout' => 30,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visitor identity
    |--------------------------------------------------------------------------
    | <x-chat-hybrid-ai-bubble :identify="auth()->user()" /> signs the user's
    | id with the app key. Tools trust only that id, never the name, email or
    | meta a visitor sends. A chat started with a valid identity keeps it.
    */
    'identity' => [
        'ttl' => 86400, // seconds a signed identity stays valid
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcripts
    |--------------------------------------------------------------------------
    | Visitors and admins can email the full conversation. On close, the
    | transcript can be sent automatically to the visitor and/or a list of
    | support addresses.
    */
    'transcripts' => [
        'queue' => true,
        'on_close' => [
            'visitor' => env('HYBRID_CHAT_TRANSCRIPT_VISITOR', false),
            'recipients' => array_filter(explode(',', (string) env('HYBRID_CHAT_TRANSCRIPT_TO', ''))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    | Emailed when a conversation is handed off to a human.
    */
    'notify' => [
        'handoff' => array_filter(explode(',', (string) env('HYBRID_CHAT_NOTIFY', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    | The bubble and the admin page poll by default. Turn this on to also
    | broadcast MessageCreated on chat.conversation.{uuid} (visitor, public)
    | and chat.admin (private) channels for Reverb/Pusher users.
    */
    'broadcasting' => [
        'enabled' => env('HYBRID_CHAT_BROADCAST', false),
    ],
];
