<?php

namespace Siberfx\ChatHybridAi\Ai\Drivers;

use Anthropic\Client;
use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Ai\ChatTurn;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

/**
 * Claude via the official Anthropic PHP SDK.
 */
class AnthropicDriver implements AiAgent
{
    protected ?Client $client = null;

    public function __construct(protected array $config) {}

    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        $fallbacks = $this->config['fallbacks'] ?? null;

        $message = $this->client()->beta->messages->create(
            model: $this->config['model'] ?? 'claude-opus-5-5',
            maxTokens: (int) ($this->config['max_tokens'] ?? 4000),
            system: $systemPrompt,
            messages: array_map(fn (ChatTurn $turn) => $turn->toArray(), $turns),
            outputConfig: ['effort' => $this->config['effort'] ?? 'low'],
            fallbacks: $fallbacks,
            betas: $fallbacks ? ['server-side-fallback-2026-07-01'] : null,
            metadata: ['userID' => $conversation->uuid],
        );

        if ($message->stopReason === 'refusal') {
            return new AiReply('', handoff: true, meta: [
                'driver' => 'anthropic',
                'refusal' => $message->stopDetails?->category ?? null,
            ]);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AiReply(trim($text), meta: [
            'driver' => 'anthropic',
            'model' => $message->model,
            'stop_reason' => $message->stopReason,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ]);
    }

    protected function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->config['api_key'] ?? null);
    }
}
