<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Illuminate\Support\Collection;
use Siberfx\ChatHybridAi\Ai\TranscriptTurns;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Models\Message;
use Siberfx\ChatHybridAi\Tests\TestCase;

class TranscriptTurnsTest extends TestCase
{
    public function test_builds_alternating_turns(): void
    {
        $messages = Collection::make([
            [SenderType::Ai, 'Welcome!'],
            [SenderType::Visitor, 'Hi'],
            [SenderType::Visitor, 'Are you there?'],
            [SenderType::System, 'Ada joined the conversation.'],
            [SenderType::Agent, 'Yes, Ada here.'],
            [SenderType::Ai, 'Anything else?'],
            [SenderType::Visitor, 'No thanks'],
        ])->map(fn ($m) => new Message(['sender_type' => $m[0], 'body' => $m[1]]));

        $turns = array_map(fn ($t) => $t->toArray(), TranscriptTurns::from($messages));

        $this->assertSame([
            ['role' => 'user', 'content' => "Hi\n\nAre you there?"],
            ['role' => 'assistant', 'content' => "Yes, Ada here.\n\nAnything else?"],
            ['role' => 'user', 'content' => 'No thanks'],
        ], $turns);
    }
}
