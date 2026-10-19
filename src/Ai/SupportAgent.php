<?php

namespace Siberfx\ChatHybridAi\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * The laravel/ai agent behind the "laravel-ai" driver. Fake it in your tests
 * with SupportAgent::fake([...]).
 */
class SupportAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(
        protected string $instructions,
        protected array $messages = [],
        protected array $tools = [],
        protected array $config = [],
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    /** Model round trips per reply, tool calls included. */
    public function maxSteps(): int
    {
        return (int) ($this->config['max_steps'] ?? 5);
    }

    public function maxTokens(): ?int
    {
        return isset($this->config['max_tokens']) ? (int) $this->config['max_tokens'] : null;
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 60);
    }

    public function providerOptions(Lab|string $provider): array
    {
        $name = $provider instanceof Lab ? $provider->value : $provider;

        return (array) ($this->config['provider_options'][$name] ?? []);
    }
}
