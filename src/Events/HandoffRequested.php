<?php

namespace Siberfx\ChatHybridAi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Siberfx\ChatHybridAi\Models\Conversation;

class HandoffRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $reason  visitor | ai | ai_error | refusal
     */
    public function __construct(public Conversation $conversation, public string $reason) {}
}
