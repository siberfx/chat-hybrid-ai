<?php

namespace Siberfx\ChatHybridAi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Siberfx\ChatHybridAi\Ai\TranscriptTurns;
use Siberfx\ChatHybridAi\ChatManager;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Support\ChatModels;
use Throwable;

class GenerateAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $backoff = 5;

    public int $timeout = 300;

    public function __construct(
        public int $conversationId,
        public int $triggerMessageId,
    ) {}

    public function handle(ChatManager $chat): void
    {
        $conversation = $this->conversation();

        if (! $conversation?->isAiActive()) {
            return;
        }

        // The visitor sent more messages meanwhile: the job for the newest one answers all of them.
        $latestVisitorId = (int) $conversation->messages()->where('sender_type', SenderType::Visitor)->max('id');
        if ($latestVisitorId !== $this->triggerMessageId) {
            return;
        }

        $messages = $conversation->messages()
            ->latest('id')
            ->limit((int) config('chat-hybrid-ai.ai.history_limit', 30))
            ->get()
            ->reverse()
            ->values();

        $turns = TranscriptTurns::from($messages);

        if ($turns === [] || end($turns)->role !== 'user') {
            return;
        }

        $reply = $chat->ai()->driver()->reply(
            $conversation,
            $turns,
            (string) config('chat-hybrid-ai.ai.system_prompt'),
        );

        // A human may have taken over while the model was thinking.
        $conversation->refresh();
        if ($reply === null || ! $conversation->isAiActive()) {
            return;
        }

        $marker = (string) config('chat-hybrid-ai.ai.handoff_marker', '[[HANDOFF]]');
        $handoff = $reply->handoff;
        $text = $reply->text;

        if ($marker !== '' && Str::contains($text, $marker)) {
            $handoff = true;
            $text = trim(str_replace($marker, '', $text));
        }

        if ($text !== '') {
            $chat->aiMessage($conversation, $text, $reply->meta);
        }

        if ($handoff) {
            $chat->requestHuman($conversation, isset($reply->meta['refusal']) ? 'refusal' : 'ai');
        }
    }

    /**
     * The AI is unavailable: don't leave the visitor waiting on nobody.
     */
    public function failed(?Throwable $e): void
    {
        $conversation = $this->conversation();

        if ($conversation?->isAiActive()) {
            app(ChatManager::class)->requestHuman($conversation, 'ai_error');
        }

        if ($e) {
            report($e);
        }
    }

    protected function conversation(): ?Conversation
    {
        return ChatModels::conversation()::find($this->conversationId);
    }
}
