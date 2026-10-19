<?php

namespace Siberfx\ChatHybridAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Support\ChatModels;

/**
 * @property int $id
 * @property int $conversation_id
 * @property SenderType $sender_type
 * @property ?int $sender_id
 * @property string $body
 * @property ?array $meta
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sender_type' => SenderType::class,
            'meta' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('chat-hybrid-ai.tables.messages', 'chat_messages');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatModels::conversation(), 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(ChatModels::agent(), 'sender_id');
    }

    /** Display name of whoever sent the message. */
    public function senderName(): string
    {
        return match ($this->sender_type) {
            SenderType::Visitor => $this->conversation?->visitor_name ?: __('Visitor'),
            SenderType::Agent => $this->sender?->name ?? __('Support'),
            SenderType::Ai => __('Assistant'),
            SenderType::System => __('System'),
        };
    }
}
