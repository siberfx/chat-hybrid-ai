<?php

namespace Siberfx\ChatHybridAi\Ai;

use Illuminate\Support\Collection;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Models\Message;

/**
 * Turns stored chat messages into a clean user/assistant transcript:
 * system notes are dropped, consecutive messages from the same side are
 * merged, and leading assistant turns are removed so the transcript
 * starts (and, when called for a reply, ends) with the visitor.
 */
class TranscriptTurns
{
    /**
     * @param  Collection<int, Message>  $messages  Oldest first.
     * @return list<ChatTurn>
     */
    public static function from(Collection $messages): array
    {
        $turns = [];

        foreach ($messages as $message) {
            $role = match ($message->sender_type) {
                SenderType::Visitor => 'user',
                SenderType::Agent, SenderType::Ai => 'assistant',
                default => null,
            };

            if ($role === null || trim($message->body) === '') {
                continue;
            }

            if ($turns === [] && $role === 'assistant') {
                continue;
            }

            $last = array_key_last($turns);

            if ($last !== null && $turns[$last]->role === $role) {
                $turns[$last] = new ChatTurn($role, $turns[$last]->content."\n\n".$message->body);
            } else {
                $turns[] = new ChatTurn($role, $message->body);
            }
        }

        return $turns;
    }
}
