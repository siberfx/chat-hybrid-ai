<?php

namespace Siberfx\ChatHybridAi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The visitor's view of a conversation.
 *
 * @mixin \Siberfx\ChatHybridAi\Models\Conversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'mode' => $this->mode->value,
            'visitor_name' => $this->visitor_name,
            'visitor_email' => $this->visitor_email,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
