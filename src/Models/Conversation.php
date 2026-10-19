<?php

namespace Siberfx\ChatHybridAi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Siberfx\ChatHybridAi\Enums\ConversationMode;
use Siberfx\ChatHybridAi\Enums\ConversationStatus;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Support\ChatModels;

/**
 * @property int $id
 * @property string $uuid
 * @property string $token
 * @property ?string $visitor_name
 * @property ?string $visitor_email
 * @property ?string $subject
 * @property ConversationStatus $status
 * @property ConversationMode $mode
 * @property ?int $assigned_to
 * @property ?array $meta
 * @property ?\Illuminate\Support\Carbon $visitor_last_read_at
 * @property ?\Illuminate\Support\Carbon $admin_last_read_at
 * @property ?\Illuminate\Support\Carbon $last_message_at
 * @property ?\Illuminate\Support\Carbon $closed_at
 */
class Conversation extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected $attributes = [
        'status' => 'open',
        'mode' => 'ai',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'mode' => ConversationMode::class,
            'meta' => 'array',
            'visitor_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-hybrid-ai.tables.conversations', 'chat_conversations');
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->uuid ??= (string) Str::uuid();
            $conversation->token ??= Str::random(64);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatModels::message(), 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatModels::message(), 'conversation_id')->latestOfMany();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ChatModels::agent(), 'assigned_to');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open);
    }

    public function scopeMode(Builder $query, ConversationMode $mode): Builder
    {
        return $query->where('mode', $mode);
    }

    /** Conversations with visitor messages the admins have not read yet. */
    public function scopeUnreadByAdmin(Builder $query): Builder
    {
        $messages = (new (ChatModels::message()))->getTable();

        return $query->whereExists(function ($q) use ($messages) {
            $q->selectRaw('1')
                ->from($messages)
                ->whereColumn("{$messages}.conversation_id", $this->getTable().'.id')
                ->where("{$messages}.sender_type", SenderType::Visitor->value)
                ->where(function ($q) use ($messages) {
                    $q->whereNull($this->getTable().'.admin_last_read_at')
                        ->orWhereColumn("{$messages}.created_at", '>', $this->getTable().'.admin_last_read_at');
                });
        });
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    public function isAiActive(): bool
    {
        return $this->isOpen() && $this->mode === ConversationMode::Ai;
    }

    public function unreadForVisitorCount(): int
    {
        return $this->messages()
            ->where('sender_type', '!=', SenderType::Visitor)
            ->when($this->visitor_last_read_at, fn ($q, $at) => $q->where('created_at', '>', $at))
            ->count();
    }

    public function unreadForAdminCount(): int
    {
        return $this->messages()
            ->where('sender_type', SenderType::Visitor)
            ->when($this->admin_last_read_at, fn ($q, $at) => $q->where('created_at', '>', $at))
            ->count();
    }
}
