<?php

namespace Siberfx\ChatHybridAi\Ai;

final class ChatTurn
{
    public function __construct(
        public readonly string $role, // user | assistant
        public readonly string $content,
    ) {}

    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
