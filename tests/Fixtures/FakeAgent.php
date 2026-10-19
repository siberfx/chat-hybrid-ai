<?php

namespace Siberfx\ChatHybridAi\Tests\Fixtures;

use RuntimeException;
use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

class FakeAgent implements AiAgent
{
    /** @var list<array{turns: array, system: string}> */
    public array $calls = [];

    public function __construct(public ?string $answer = 'Hello from the bot', public bool $fail = false) {}

    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        $this->calls[] = ['turns' => $turns, 'system' => $systemPrompt];

        if ($this->fail) {
            throw new RuntimeException('AI is down');
        }

        return $this->answer === null ? null : new AiReply($this->answer);
    }
}
