<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Siberfx\ChatHybridAi\Ai\ChatContext;
use Siberfx\ChatHybridAi\Ai\SupportAgent;
use Siberfx\ChatHybridAi\Ai\Tools\FindRecord;
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Tests\Fixtures\Order;
use Siberfx\ChatHybridAi\Tests\TestCase;

class LaravelAiDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('chat-hybrid-ai.ai.driver', 'laravel-ai');
    }

    protected function setUp(): void
    {
        parent::setUp();

        HybridChat::ai()->forgetDrivers();
        Order::create(['number' => 'A-1', 'user_id' => 5, 'email' => 'vera@example.com', 'status' => 'shipped']);
        HybridChat::tools(fn (ChatContext $context) => [
            FindRecord::model(Order::class)->by('number')->ownedBy('user_id')->show(['number', 'status']),
        ]);
    }

    public function test_agent_uses_a_tool_and_admins_see_the_lookup(): void
    {
        SupportAgent::fake([
            new ToolCall('call_1', 'find_order', ['number' => 'A-1']),
            'Your order A-1 has shipped.',
        ]);

        $conversation = HybridChat::start(['visitor_name' => 'Vera', 'visitor_id' => '5'], 'Where is order A-1?');

        $reply = $conversation->messages()->latest('id')->first();
        $this->assertSame('ai', $reply->sender_type->value);
        $this->assertSame('Your order A-1 has shipped.', $reply->body);
        $this->assertSame([['name' => 'find_order', 'arguments' => ['number' => 'A-1']]], $reply->meta['tools']);

        SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'Where is order A-1?'
            && str_contains($prompt->agent->instructions(), 'Signed in: yes')
            && str_contains($prompt->agent->instructions(), 'Name: Vera'));

        $this->actingAs($this->admin())
            ->getJson("/chat-admin/api/conversations/{$conversation->uuid}")
            ->assertJsonPath('messages.1.tools.0.name', 'find_order');

        $this->withHeader('X-Chat-Token', $conversation->token)
            ->getJson("/api/chat/conversations/{$conversation->uuid}")
            ->assertJsonMissingPath('messages.1.tools');
    }

    public function test_agent_can_hand_off_with_the_request_human_tool(): void
    {
        SupportAgent::fake([
            new ToolCall('call_1', 'request_human', ['reason' => 'refund dispute']),
            'A colleague will join you shortly.',
        ]);

        $conversation = HybridChat::start([], 'I want my money back!');

        $this->assertSame('waiting', $conversation->fresh()->mode->value);
        $this->assertSame(['visitor', 'ai', 'system'], $conversation->messages()->orderBy('id')->pluck('sender_type')->map->value->all());
    }

    public function test_history_is_passed_as_messages(): void
    {
        SupportAgent::fake(['First answer', 'Second answer']);

        $conversation = HybridChat::start([], 'First question');
        HybridChat::visitorMessage($conversation, 'Second question');

        SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'Second question'
            && collect($prompt->agent->messages())->pluck('content')->all() === ['First question', 'First answer']);
    }
}
