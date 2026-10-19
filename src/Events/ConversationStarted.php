<?php

namespace Siberfx\ChatHybridAi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Siberfx\ChatHybridAi\Models\Conversation;

class ConversationStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Conversation $conversation) {}
}
