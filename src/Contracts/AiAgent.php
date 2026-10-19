<?php

namespace Siberfx\ChatHybridAi\Contracts;

use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Ai\ChatTurn;
use Siberfx\ChatHybridAi\Models\Conversation;

interface AiAgent
{
    /**
     * Produce the next assistant reply. Return null to stay silent.
     *
     * @param  list<ChatTurn>  $turns  Alternating user/assistant turns, oldest first, ending with a user turn.
     */
    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply;
}
