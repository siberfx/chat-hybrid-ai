<?php

namespace Siberfx\ChatHybridAi\Ai;

use Illuminate\Support\Manager;
use Siberfx\ChatHybridAi\Ai\Drivers\LaravelAiDriver;
use Siberfx\ChatHybridAi\Ai\Drivers\NullDriver;
use Siberfx\ChatHybridAi\Ai\Drivers\WebhookDriver;
use Siberfx\ChatHybridAi\Contracts\AiAgent;

/**
 * @method AiAgent driver(?string $driver = null)
 */
class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('chat-hybrid-ai.ai.driver', 'null');
    }

    protected function driverConfig(string $name): array
    {
        return (array) $this->config->get("chat-hybrid-ai.ai.drivers.{$name}", []);
    }

    protected function createLaravelAiDriver(): AiAgent
    {
        return new LaravelAiDriver($this->driverConfig('laravel-ai'));
    }

    protected function createWebhookDriver(): AiAgent
    {
        return new WebhookDriver($this->driverConfig('webhook'));
    }

    protected function createNullDriver(): AiAgent
    {
        return new NullDriver;
    }

    /**
     * Custom drivers receive the app and their config block.
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->container, $this->driverConfig($driver));
    }
}
