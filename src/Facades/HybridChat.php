<?php

namespace Siberfx\ChatHybridAi\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\ChatHybridAi\ChatManager;

/**
 * @method static \Siberfx\ChatHybridAi\Models\Conversation start(array $attributes = [], ?string $firstMessage = null)
 * @method static \Siberfx\ChatHybridAi\Models\Message visitorMessage(\Siberfx\ChatHybridAi\Models\Conversation $conversation, string $body, array $meta = [])
 * @method static \Siberfx\ChatHybridAi\Models\Message agentMessage(\Siberfx\ChatHybridAi\Models\Conversation $conversation, \Illuminate\Contracts\Auth\Authenticatable $agent, string $body)
 * @method static \Siberfx\ChatHybridAi\Models\Message aiMessage(\Siberfx\ChatHybridAi\Models\Conversation $conversation, string $body, array $meta = [])
 * @method static \Siberfx\ChatHybridAi\Models\Message systemMessage(\Siberfx\ChatHybridAi\Models\Conversation $conversation, string $body, array $meta = [])
 * @method static \Siberfx\ChatHybridAi\Models\Conversation requestHuman(\Siberfx\ChatHybridAi\Models\Conversation $conversation, string $reason = 'visitor')
 * @method static \Siberfx\ChatHybridAi\Models\Conversation takeOver(\Siberfx\ChatHybridAi\Models\Conversation $conversation, \Illuminate\Contracts\Auth\Authenticatable $agent)
 * @method static \Siberfx\ChatHybridAi\Models\Conversation returnToAi(\Siberfx\ChatHybridAi\Models\Conversation $conversation)
 * @method static \Siberfx\ChatHybridAi\Models\Conversation close(\Siberfx\ChatHybridAi\Models\Conversation $conversation)
 * @method static \Siberfx\ChatHybridAi\Models\Conversation reopen(\Siberfx\ChatHybridAi\Models\Conversation $conversation)
 * @method static void sendTranscript(\Siberfx\ChatHybridAi\Models\Conversation $conversation, string|array $to)
 * @method static \Siberfx\ChatHybridAi\ChatManager extendAi(string $driver, \Closure $callback)
 * @method static \Siberfx\ChatHybridAi\Ai\AiManager ai()
 * @method static bool aiEnabled()
 * @method static \Siberfx\ChatHybridAi\ChatManager tools(\Closure|array $tools)
 * @method static array toolsFor(\Siberfx\ChatHybridAi\Ai\ChatContext $context)
 * @method static string identity(\Illuminate\Database\Eloquent\Model|int|string $user, ?int $ttl = null)
 *
 * @see ChatManager
 */
class HybridChat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatManager::class;
    }
}
