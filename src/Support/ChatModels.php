<?php

namespace Siberfx\ChatHybridAi\Support;

use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Models\Message;

/**
 * Resolves the (overridable) model classes from config.
 */
class ChatModels
{
    /** @return class-string<Conversation> */
    public static function conversation(): string
    {
        return config('chat-hybrid-ai.models.conversation') ?: Conversation::class;
    }

    /** @return class-string<Message> */
    public static function message(): string
    {
        return config('chat-hybrid-ai.models.message') ?: Message::class;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function agent(): string
    {
        return config('chat-hybrid-ai.models.agent')
            ?: config('auth.providers.users.model', 'App\Models\User');
    }
}
