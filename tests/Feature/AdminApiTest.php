<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Mail\TranscriptMail;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Tests\Fixtures\User;
use Siberfx\ChatHybridAi\Tests\TestCase;

class AdminApiTest extends TestCase
{
    protected function conversation(): Conversation
    {
        return HybridChat::start(['visitor_name' => 'Vera', 'visitor_email' => 'vera@example.com'], 'I need help with my order');
    }

    public function test_admin_is_protected_by_auth_and_gate(): void
    {
        $this->getJson('/chat-admin/api/stats')->assertUnauthorized();

        $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->actingAs($user)->get('/chat-admin')->assertForbidden();

        $this->actingAs($this->admin())->get('/chat-admin')->assertOk()->assertSee('Chat inbox');
    }

    public function test_token_api_is_off_without_sanctum(): void
    {
        $this->assertTrue(app('router')->has('chat-hybrid-ai.admin.stats'));
        $this->assertFalse(app('router')->has('chat-hybrid-ai.admin-api.stats'));
    }

    public function test_lists_and_filters_conversations(): void
    {
        $ai = $this->conversation();
        $waiting = HybridChat::requestHuman($this->conversation());

        $this->actingAs($this->admin());

        $this->getJson('/chat-admin/api/conversations?status=open')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/chat-admin/api/conversations?mode=waiting')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $waiting->uuid);
        $this->getJson('/chat-admin/api/conversations?search=order')->assertJsonCount(2, 'data');
        $this->getJson('/chat-admin/api/stats')->assertJson(['open' => 2, 'ai' => 1, 'waiting' => 1, 'unread' => 2]);
    }

    public function test_agent_reply_takes_over_and_stops_the_ai(): void
    {
        $conversation = $this->conversation();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/chat-admin/api/conversations/{$conversation->uuid}/messages", ['body' => 'Hi Vera, Ada here.'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'agent')
            ->assertJsonPath('data.sender_name', 'Ada Admin');

        $conversation->refresh();
        $this->assertSame('human', $conversation->mode->value);
        $this->assertSame($admin->id, $conversation->assigned_to);

        HybridChat::visitorMessage($conversation, 'Thanks!');
        $this->assertCount(1, $this->agent->calls);
    }

    public function test_show_marks_conversation_read(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->admin());

        $this->getJson('/chat-admin/api/conversations?unread=1')->assertJsonCount(1, 'data');
        $this->getJson("/chat-admin/api/conversations/{$conversation->uuid}")->assertOk()->assertJsonCount(2, 'messages');
        $this->getJson('/chat-admin/api/conversations?unread=1')->assertJsonCount(0, 'data');
    }

    public function test_return_to_ai_close_and_reopen(): void
    {
        $conversation = $this->conversation();
        $admin = $this->admin();
        HybridChat::takeOver($conversation, $admin);
        $this->actingAs($admin);
        $base = "/chat-admin/api/conversations/{$conversation->uuid}";

        $this->postJson("{$base}/return-to-ai")->assertOk()->assertJsonPath('data.mode', 'ai');
        $this->postJson("{$base}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson("{$base}/reopen")->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_admin_emails_transcript_to_any_addresses(): void
    {
        Mail::fake();
        $conversation = $this->conversation();
        $this->actingAs($this->admin());

        $this->postJson("/chat-admin/api/conversations/{$conversation->uuid}/transcript", ['emails' => ['a@example.com', 'b@example.com']])
            ->assertOk();
        $this->postJson("/chat-admin/api/conversations/{$conversation->uuid}/transcript")
            ->assertOk()
            ->assertJson(['emails' => ['vera@example.com']]);

        Mail::assertQueued(TranscriptMail::class, 2);
    }

    public function test_delete_conversation(): void
    {
        $conversation = $this->conversation();

        $this->actingAs($this->admin())->deleteJson("/chat-admin/api/conversations/{$conversation->uuid}")->assertNoContent();

        $this->assertSoftDeleted($conversation);
    }
}
