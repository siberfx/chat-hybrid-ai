<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Siberfx\ChatHybridAi\Facades\HybridChat;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Support\VisitorIdentity;
use Siberfx\ChatHybridAi\Tests\Fixtures\User;
use Siberfx\ChatHybridAi\Tests\TestCase;

class VisitorIdentityTest extends TestCase
{
    public function test_identity_round_trips_and_rejects_forgeries(): void
    {
        $token = HybridChat::identity(42);

        $this->assertSame('42', VisitorIdentity::verify($token));
        $this->assertNull(VisitorIdentity::verify(null));
        $this->assertNull(VisitorIdentity::verify('garbage'));

        [$hex, $expires, $signature] = explode('.', $token);
        $this->assertNull(VisitorIdentity::verify(bin2hex('43').".{$expires}.{$signature}"));
        $this->assertNull(VisitorIdentity::verify("{$hex}.".($expires + 999).".{$signature}"));

        $this->travel(2)->days();
        $this->assertNull(VisitorIdentity::verify($token));
    }

    public function test_widget_trusts_only_a_signed_identity(): void
    {
        $signed = $this->postJson('/api/chat/conversations', ['message' => 'Hi', 'identity' => HybridChat::identity(7)])
            ->assertCreated()
            ->assertJsonPath('conversation.identified', true);

        $this->assertSame('7', Conversation::where('uuid', $signed->json('conversation.id'))->value('visitor_id'));

        $forged = $this->postJson('/api/chat/conversations', ['message' => 'Hi', 'identity' => bin2hex('7').'.9999999999.abc', 'meta' => ['user_id' => '7']])
            ->assertCreated()
            ->assertJsonPath('conversation.identified', false);

        $this->assertNull(Conversation::where('uuid', $forged->json('conversation.id'))->value('visitor_id'));
    }

    public function test_guest_can_sign_in_mid_chat_but_the_chat_never_changes_hands(): void
    {
        $start = $this->postJson('/api/chat/conversations', ['message' => 'Hi'])->json();
        $url = "/api/chat/conversations/{$start['conversation']['id']}";
        $headers = ['X-Chat-Token' => $start['token']];

        $this->patchJson($url, ['identity' => HybridChat::identity(7)], $headers)->assertJsonPath('data.identified', true);
        $this->patchJson($url, ['identity' => HybridChat::identity(8)], $headers);

        $this->assertSame('7', Conversation::where('uuid', $start['conversation']['id'])->value('visitor_id'));
    }

    public function test_bubble_signs_the_given_user(): void
    {
        $user = User::create(['name' => 'Vera', 'email' => 'vera@example.com']);

        $html = Blade::render('<x-chat-hybrid-ai-bubble :identify="$user" />', ['user' => $user]);

        preg_match('/data-identity="([^"]+)"/', $html, $match);
        $this->assertSame((string) $user->id, VisitorIdentity::verify($match[1] ?? null));
        $this->assertStringNotContainsString('data-identity', Blade::render('<x-chat-hybrid-ai-bubble />'));
    }
}
