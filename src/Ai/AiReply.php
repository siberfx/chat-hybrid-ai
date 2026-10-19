<?php

namespace Siberfx\ChatHybridAi\Ai;

final class AiReply
{
    public function __construct(
        public readonly string $text,
        public readonly bool $handoff = false,
        public readonly array $meta = [],
    ) {}
}
