<?php

namespace Siberfx\ChatHybridAi\Ai\Drivers;

use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

class NullDriver implements AiAgent
{
    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        return null;
    }
}
