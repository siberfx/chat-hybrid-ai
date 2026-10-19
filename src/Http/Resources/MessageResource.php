<?php

namespace Siberfx\ChatHybridAi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Siberfx\ChatHybridAi\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type->value,
            'sender_name' => $this->senderName(),
            'body' => $this->body,
            'event' => $this->meta['event'] ?? null,
            // What the AI looked up for this reply: admins only.
            'tools' => $this->when(
                $request->routeIs('chat-hybrid-ai.admin.*', 'chat-hybrid-ai.admin-api.*'),
                fn () => $this->meta['tools'] ?? [],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
