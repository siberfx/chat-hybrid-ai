<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models & tables
    |--------------------------------------------------------------------------
    | Swap in your own models (they should extend the package models) and
    | table names. The agent model is the user model of your admins.
    */
    'models' => [
        'conversation' => Siberfx\ChatHybridAi\Models\Conversation::class,
        'message' => Siberfx\ChatHybridAi\Models\Message::class,
        'agent' => null, // defaults to auth.providers.users.model
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
    | Drivers: anthropic, openai (any OpenAI-compatible endpoint), webhook
    | (your own agent service), null. Or register your own with
    | HybridChat::extendAi('name', fn ($app, $config) => new MyDriver).
    */
    'ai' => [
        'enabled' => env('HYBRID_CHAT_AI', true),
        'driver' => env('HYBRID_CHAT_AI_DRIVER', 'anthropic'),
        'queue' => env('HYBRID_CHAT_AI_QUEUE'), // null = default queue
        'history_limit' => 30,
        'handoff_marker' => '[[HANDOFF]]',

        'system_prompt' => env('HYBRID_CHAT_AI_PROMPT',
            "You are a friendly customer support assistant for this website. Answer briefly and clearly. "
            ."If you cannot help, the visitor asks for a human, or the request involves billing disputes, "
            ."account access, or anything you are unsure about, reply with a short message saying a team member "
            ."will take over and end your reply with [[HANDOFF]]."
        ),

        'drivers' => [
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('HYBRID_CHAT_ANTHROPIC_MODEL', 'claude-opus-5-5'),
                'max_tokens' => 4000,
                'effort' => 'low', // chat replies rarely need deep thinking
                'fallbacks' => 'default', // server-side fallback when the model declines; null to disable
            ],

            'openai' => [
                'api_key' => env('HYBRID_CHAT_OPENAI_KEY'),
                'base_url' => env('HYBRID_CHAT_OPENAI_URL', 'https://api.openai.com/v1'),
                'model' => env('HYBRID_CHAT_OPENAI_MODEL'),
                'max_tokens' => 1000,
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
