<?php

namespace Siberfx\ChatHybridAi\Ai;

use Illuminate\Database\Eloquent\Model;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Support\ChatModels;

/**
 * What a tool may know about the chat it is answering. Only the visitor id is
 * trusted: it comes from a signed identity. Name, email and meta are typed by
 * the visitor, so never use them to decide whose records to show.
 */
class ChatContext
{
    protected ?Model $visitor = null;

    public function __construct(public readonly Conversation $conversation) {}

    /**
     * The context of the reply being generated, inside a tool.
     */
    public static function current(): ?self
    {
        return app()->bound(self::class) ? app(self::class) : null;
    }

    /** The verified id of the signed-in visitor, or null for guests. */
    public function visitorId(): ?string
    {
        return $this->conversation->visitor_id;
    }

    public function isIdentified(): bool
    {
        return $this->visitorId() !== null;
    }

    /** The signed-in visitor's model (models.visitor), or null for guests. */
    public function visitor(): ?Model
    {
        if (! $this->isIdentified()) {
            return null;
        }

        return $this->visitor ??= ChatModels::visitor()::find($this->visitorId());
    }
}
