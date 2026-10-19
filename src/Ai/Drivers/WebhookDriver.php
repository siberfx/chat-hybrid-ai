<?php

namespace Siberfx\ChatHybridAi\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Siberfx\ChatHybridAi\Ai\AiReply;
use Siberfx\ChatHybridAi\Ai\ChatTurn;
use Siberfx\ChatHybridAi\Contracts\AiAgent;
use Siberfx\ChatHybridAi\Models\Conversation;

/**
 * Forwards the conversation to your own agent service (n8n, a Python agent,
 * a Managed Agents bridge, ...).
 *
 * Request:  POST {url} JSON {conversation, system_prompt, messages}
 *           header X-Chat-Signature: sha256 HMAC of the raw body with the secret
 * Response: {"reply": "text", "handoff": false, "meta": {}}  (reply may be null)
 */
class WebhookDriver implements AiAgent
{
    public function __construct(protected array $config) {}

    public function reply(Conversation $conversation, array $turns, string $systemPrompt): ?AiReply
    {
        $payload = json_encode([
            'conversation' => [
                'id' => $conversation->uuid,
                'visitor_name' => $conversation->visitor_name,
                'visitor_email' => $conversation->visitor_email,
                'subject' => $conversation->subject,
                'meta' => $conversation->meta,
            ],
            'system_prompt' => $systemPrompt,
            'messages' => array_map(fn (ChatTurn $turn) => $turn->toArray(), $turns),
        ], JSON_THROW_ON_ERROR);

        $request = Http::timeout((int) ($this->config['timeout'] ?? 30))->acceptJson();

        if (! empty($this->config['secret'])) {
            $request = $request->withHeaders([
                'X-Chat-Signature' => hash_hmac('sha256', $payload, $this->config['secret']),
            ]);
        }

        $response = $request
            ->withBody($payload, 'application/json')
            ->post($this->config['url'])
            ->throw();

        $reply = $response->json('reply');

        if ($reply === null && ! $response->json('handoff')) {
            return null;
        }

        return new AiReply(
            trim((string) $reply),
            handoff: (bool) $response->json('handoff', false),
            meta: ['driver' => 'webhook'] + (array) $response->json('meta', []),
        );
    }
}
