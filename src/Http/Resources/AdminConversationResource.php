<?php

namespace Siberfx\ChatHybridAi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \Siberfx\ChatHybridAi\Models\Conversation */
class AdminConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'mode' => $this->mode->value,
            'visitor_name' => $this->visitor_name,
            'visitor_email' => $this->visitor_email,
            'subject' => $this->subject,
            'meta' => $this->meta,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->getKey(),
                'name' => $this->assignee->name ?? null,
            ] : null),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'sender_type' => $this->latestMessage->sender_type->value,
                'body' => Str::limit($this->latestMessage->body, 120),
            ] : null),
            'unread' => $this->unreadForAdminCount(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
