<?php

namespace Siberfx\ChatHybridAi\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Siberfx\ChatHybridAi\Http\Resources\MessageResource;
use Siberfx\ChatHybridAi\Models\Message;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastWhen(): bool
    {
        return (bool) config('chat-hybrid-ai.broadcasting.enabled');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('chat.conversation.'.$this->message->conversation->uuid),
            new PrivateChannel('chat.admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => $this->message->conversation->uuid,
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
