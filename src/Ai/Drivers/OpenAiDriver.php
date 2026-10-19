<?php

namespace Siberfx\ChatHybridAi\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Ai\ChatTurn;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

/**
 * Any OpenAI-compatible /chat/completions endpoint (OpenAI, Groq, Ollama,
 * OpenRouter, LM Studio, ...).
 */
class OpenAiDriver implements AiAgent
{
    public function __construct(protected array $config) {}

    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($turns as $turn) {
            /** @var ChatTurn $turn */
            $messages[] = $turn->toArray();
        }

        $response = Http::baseUrl(rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/'))
            ->withToken((string) ($this->config['api_key'] ?? ''))
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->post('chat/completions', [
                'model' => $this->config['model'],
                'messages' => $messages,
                'max_tokens' => (int) ($this->config['max_tokens'] ?? 1000),
                'user' => $conversation->uuid,
            ])
            ->throw();

        return new AiReply(trim((string) $response->json('choices.0.message.content')), meta: [
            'driver' => 'openai',
            'model' => $response->json('model'),
            'input_tokens' => $response->json('usage.prompt_tokens'),
            'output_tokens' => $response->json('usage.completion_tokens'),
        ]);
    }
}
