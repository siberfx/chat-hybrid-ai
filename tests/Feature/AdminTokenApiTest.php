<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Tests\Fixtures\User;
use Siberfx\ChatHybridAi\Tests\TestCase;

class AdminTokenApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Sanctum isn't installed here: any stateless guard proves the wiring.
        $app['config']->set('chat-hybrid-ai.routes.admin_api', [
            'enabled' => true,
            'prefix' => 'api/chat-admin',
            'middleware' => ['api', 'auth'],
        ]);
    }

    public function test_token_api_is_protected_by_auth_and_gate(): void
    {
        $this->getJson('/api/chat-admin/stats')->assertUnauthorized();

        $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->actingAs($user)->getJson('/api/chat-admin/stats')->assertForbidden();
    }

    public function test_admin_can_work_a_conversation_over_the_token_api(): void
    {
        $conversation = HybridChat::start(['visitor_name' => 'Vera'], 'Where is my order?');
        $this->actingAs($this->admin());
        $base = "/api/chat-admin/conversations/{$conversation->uuid}";

        $this->getJson('/api/chat-admin/config')->assertOk()->assertJson(['ai' => true]);
        $this->getJson('/api/chat-admin/conversations')->assertOk()->assertJsonPath('data.0.id', $conversation->uuid);
        $this->getJson($base)->assertOk()->assertJsonCount(2, 'messages');

        $this->postJson("{$base}/messages", ['body' => 'On its way!'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'agent');

        $this->postJson("{$base}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    }
}
