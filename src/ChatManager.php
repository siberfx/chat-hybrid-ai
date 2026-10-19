<?php

namespace Siberfx\ChatHybridAi;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Siberfx\ChatHybridAi\Ai\AiManager;
use Siberfx\ChatHybridAi\Ai\ChatContext;
use Siberfx\ChatHybridAi\Enums\ConversationMode;
use Siberfx\ChatHybridAi\Enums\ConversationStatus;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Events\ConversationClosed;
use Siberfx\ChatHybridAi\Events\ConversationStarted;
use Siberfx\ChatHybridAi\Events\HandoffRequested;
use Siberfx\ChatHybridAi\Events\MessageCreated;
use Siberfx\ChatHybridAi\Jobs\GenerateAiReply;
use Siberfx\ChatHybridAi\Mail\HandoffRequestedMail;
use Siberfx\ChatHybridAi\Mail\TranscriptMail;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Models\Message;
use Siberfx\ChatHybridAi\Support\ChatModels;
use Siberfx\ChatHybridAi\Support\VisitorIdentity;

/**
 * Every state change of a conversation goes through here, so the HTTP layer,
 * jobs and your own code behave the same way.
 */
class ChatManager
{
    /** @var list<Closure|array> */
    protected array $tools = [];

    public function __construct(protected AiManager $ai) {}

    public function ai(): AiManager
    {
        return $this->ai;
    }

    /**
     * Register a custom AI driver: fn (Application $app, array $config): AiAgent.
     */
    public function extendAi(string $driver, Closure $callback): static
    {
        $this->ai->extend($driver, $callback);

        return $this;
    }

    /**
     * Give the AI agent tools (laravel/ai Tool instances): an array, or a
     * closure that receives the ChatContext and returns an array.
     */
    public function tools(Closure|array $tools): static
    {
        $this->tools[] = $tools;

        return $this;
    }

    /**
     * The tools for one reply: ai.tools from the config, then registered ones.
     *
     * @return list<\Laravel\Ai\Contracts\Tool|\Laravel\Ai\Providers\Tools\ProviderTool>
     */
    public function toolsFor(ChatContext $context): array
    {
        $tools = array_map(fn (string $class) => app()->make($class), (array) config('chat-hybrid-ai.ai.tools', []));

        foreach ($this->tools as $registered) {
            array_push($tools, ...array_values($registered instanceof Closure ? (array) $registered($context) : $registered));
        }

        return $tools;
    }

    /**
     * A signed identity for a logged-in user, for the bubble or your own widget.
     */
    public function identity(Model|int|string $user, ?int $ttl = null): string
    {
        return VisitorIdentity::sign($user, $ttl);
    }

    public function aiEnabled(): bool
    {
        return (bool) config('chat-hybrid-ai.ai.enabled') && config('chat-hybrid-ai.ai.driver') !== 'null';
    }

    /**
     * @param  array{visitor_name?: ?string, visitor_email?: ?string, visitor_id?: ?string, subject?: ?string, meta?: ?array}  $attributes
     */
    public function start(array $attributes = [], ?string $firstMessage = null): Conversation
    {
        $class = ChatModels::conversation();

        /** @var Conversation $conversation */
        $conversation = $class::create([
            'visitor_name' => $attributes['visitor_name'] ?? null,
            'visitor_email' => $attributes['visitor_email'] ?? null,
            'visitor_id' => isset($attributes['visitor_id']) ? (string) $attributes['visitor_id'] : null,
            'subject' => $attributes['subject'] ?? null,
            'meta' => $attributes['meta'] ?? null,
            'mode' => $this->aiEnabled() ? ConversationMode::Ai : ConversationMode::Waiting,
            'last_message_at' => now(),
        ]);

        ConversationStarted::dispatch($conversation);

        if (filled($firstMessage)) {
            $this->visitorMessage($conversation, $firstMessage);
        }

        return $conversation;
    }

    public function visitorMessage(Conversation $conversation, string $body, array $meta = []): Message
    {
        if (! $conversation->isOpen()) {
            $this->reopen($conversation);
        }

        $message = $this->addMessage($conversation, SenderType::Visitor, $body, meta: $meta);

        $conversation->forceFill(['visitor_last_read_at' => now()])->save();

        if ($conversation->isAiActive() && $this->aiEnabled()) {
            GenerateAiReply::dispatch($conversation->id, $message->id)
                ->onQueue(config('chat-hybrid-ai.ai.queue'));
        }

        return $message;
    }

    /**
     * A human agent replies. This takes the conversation over from the AI.
     */
    public function agentMessage(Conversation $conversation, Authenticatable&Model $agent, string $body): Message
    {
        if ($conversation->mode !== ConversationMode::Human || $conversation->assigned_to === null) {
            $this->takeOver($conversation, $agent);
        }

        if (! $conversation->isOpen()) {
            $this->reopen($conversation);
        }

        $message = $this->addMessage($conversation, SenderType::Agent, $body, $agent->getKey());

        $conversation->forceFill(['admin_last_read_at' => now()])->save();

        return $message;
    }

    public function aiMessage(Conversation $conversation, string $body, array $meta = []): Message
    {
        return $this->addMessage($conversation, SenderType::Ai, $body, meta: $meta);
    }

    public function systemMessage(Conversation $conversation, string $body, array $meta = []): Message
    {
        return $this->addMessage($conversation, SenderType::System, $body, meta: $meta);
    }

    /**
     * Ask for a human. Stops the AI until an agent returns the chat to it.
     */
    public function requestHuman(Conversation $conversation, string $reason = 'visitor'): Conversation
    {
        if ($conversation->mode !== ConversationMode::Ai) {
            return $conversation;
        }

        $conversation->update(['mode' => ConversationMode::Waiting]);

        $this->systemMessage($conversation, __('A team member will join the conversation shortly.'), ['event' => 'handoff', 'reason' => $reason]);

        HandoffRequested::dispatch($conversation, $reason);

        $recipients = (array) config('chat-hybrid-ai.notify.handoff', []);
        if ($recipients !== []) {
            Mail::to($recipients)->queue(new HandoffRequestedMail($conversation, $reason));
        }

        return $conversation;
    }

    public function takeOver(Conversation $conversation, Authenticatable&Model $agent): Conversation
    {
        $previous = $conversation->assigned_to;

        $conversation->update([
            'mode' => ConversationMode::Human,
            'assigned_to' => $agent->getKey(),
        ]);

        if ($previous !== $agent->getKey()) {
            $this->systemMessage($conversation, __(':name joined the conversation.', ['name' => $agent->name ?? __('Support')]), ['event' => 'joined']);
        }

        return $conversation;
    }

    public function returnToAi(Conversation $conversation): Conversation
    {
        $conversation->update(['mode' => ConversationMode::Ai, 'assigned_to' => null]);

        $this->systemMessage($conversation, __('You are now chatting with our assistant.'), ['event' => 'ai']);

        return $conversation;
    }

    public function close(Conversation $conversation): Conversation
    {
        if (! $conversation->isOpen()) {
            return $conversation;
        }

        $conversation->update(['status' => ConversationStatus::Closed, 'closed_at' => now()]);

        $this->systemMessage($conversation, __('This conversation has been closed.'), ['event' => 'closed']);

        ConversationClosed::dispatch($conversation);

        $onClose = (array) config('chat-hybrid-ai.transcripts.on_close', []);
        $recipients = (array) ($onClose['recipients'] ?? []);

        if (! empty($onClose['visitor']) && $conversation->visitor_email) {
            $recipients[] = $conversation->visitor_email;
        }

        if ($recipients !== []) {
            $this->sendTranscript($conversation, array_values(array_unique($recipients)));
        }

        return $conversation;
    }

    public function reopen(Conversation $conversation): Conversation
    {
        $conversation->update([
            'status' => ConversationStatus::Open,
            'closed_at' => null,
            'mode' => $this->aiEnabled() ? ConversationMode::Ai : ConversationMode::Waiting,
            'assigned_to' => null,
        ]);

        return $conversation;
    }

    /**
     * Email the full conversation.
     *
     * @param  string|list<string>  $to
     */
    public function sendTranscript(Conversation $conversation, string|array $to): void
    {
        $mail = Mail::to($to);
        $mailable = new TranscriptMail($conversation);

        config('chat-hybrid-ai.transcripts.queue', true) ? $mail->queue($mailable) : $mail->send($mailable);
    }

    protected function addMessage(Conversation $conversation, SenderType $type, string $body, ?int $senderId = null, array $meta = []): Message
    {
        $message = DB::transaction(function () use ($conversation, $type, $body, $senderId, $meta) {
            $message = $conversation->messages()->create([
                'sender_type' => $type,
                'sender_id' => $senderId,
                'body' => $body,
                'meta' => $meta ?: null,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        $message->setRelation('conversation', $conversation);

        MessageCreated::dispatch($message);

        return $message;
    }
}
