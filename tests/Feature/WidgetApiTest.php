<?php

namespace Siberfx\ChatHybridAi\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Siberfx\ChatHybridAi\Mail\HandoffRequestedMail;
use Siberfx\ChatHybridAi\Mail\TranscriptMail;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Tests\Fixtures\FakeAgent;
use Siberfx\ChatHybridAi\Tests\TestCase;

class WidgetApiTest extends TestCase
{
    protected function startChat(array $data = ['name' => 'Vera', 'email' => 'vera@example.com', 'message' => 'Do you ship to Turkey?'])
    {
        return $this->postJson('/api/chat/conversations', $data)->assertCreated();
    }

    public function test_widget_script_and_config_are_public(): void
    {
        $this->get('/api/chat/widget.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $this->getJson('/api/chat/config')->assertOk()->assertJson(['ai' => true, 'title' => 'Chat with us']);
    }

    public function test_visitor_starts_a_conversation_and_the_ai_answers(): void
    {
        $response = $this->startChat();

        $response->assertJsonPath('conversation.mode', 'ai')
            ->assertJsonPath('messages.0.sender_type', 'visitor')
            ->assertJsonPath('messages.1.sender_type', 'ai')
            ->assertJsonPath('messages.1.body', 'Hello from the bot');

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('user', $this->agent->calls[0]['turns'][0]->role);
        $this->assertSame('Do you ship to Turkey?', $this->agent->calls[0]['turns'][0]->content);
    }

    public function test_conversation_endpoints_require_the_token(): void
    {
        $id = $this->startChat()->json('conversation.id');

        $this->getJson("/api/chat/conversations/{$id}")->assertNotFound();
        $this->getJson("/api/chat/conversations/{$id}", ['X-Chat-Token' => 'wrong'])->assertNotFound();
        $this->postJson("/api/chat/conversations/{$id}/messages", ['body' => 'hi'])->assertNotFound();
    }

    public function test_visitor_can_poll_for_new_messages(): void
    {
        $start = $this->startChat();
        $headers = ['X-Chat-Token' => $start->json('token')];
        $id = $start->json('conversation.id');
        $lastId = $start->json('messages.1.id');

        $this->postJson("/api/chat/conversations/{$id}/messages", ['body' => 'And how long does it take?'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'visitor');

        $poll = $this->getJson("/api/chat/conversations/{$id}/messages?after={$lastId}", $headers)->assertOk();

        $this->assertSame(['visitor', 'ai'], array_column($poll->json('messages'), 'sender_type'));
        $this->assertCount(2, $this->agent->calls);
        $this->assertCount(3, $this->agent->calls[1]['turns']); // user, assistant, user
    }

    public function test_ai_handoff_marker_moves_the_chat_to_a_human(): void
    {
        Mail::fake();
        config(['chat-hybrid-ai.notify.handoff' => ['support@example.com']]);
        $this->useAgent(new FakeAgent('Let me get a colleague for you. [[HANDOFF]]'));

        $response = $this->startChat();

        $response->assertJsonPath('messages.1.body', 'Let me get a colleague for you.');
        $this->assertSame('waiting', Conversation::first()->mode->value);
        $this->assertSame('handoff', Conversation::first()->messages()->latest('id')->first()->meta['event']);
        Mail::assertQueued(HandoffRequestedMail::class, fn ($mail) => $mail->hasTo('support@example.com') && $mail->reason === 'ai');
    }

    public function test_visitor_can_ask_for_a_human_and_ai_stops_answering(): void
    {
        $start = $this->startChat();
        $headers = ['X-Chat-Token' => $start->json('token')];
        $id = $start->json('conversation.id');

        $this->postJson("/api/chat/conversations/{$id}/human", [], $headers)->assertOk()->assertJsonPath('data.mode', 'waiting');
        $this->postJson("/api/chat/conversations/{$id}/messages", ['body' => 'Hello?'], $headers)->assertCreated();

        $this->assertCount(1, $this->agent->calls);
    }

    public function test_failing_ai_hands_off_to_a_human(): void
    {
        $this->useAgent(new FakeAgent(fail: true));

        try {
            $this->startChat();
        } catch (\Throwable) {
            // the sync queue rethrows after calling failed()
        }

        $this->assertSame('waiting', Conversation::first()->mode->value);
    }

    public function test_visitor_can_email_the_transcript_to_themselves(): void
    {
        Mail::fake();
        $start = $this->startChat(['message' => 'hi']);
        $headers = ['X-Chat-Token' => $start->json('token')];
        $id = $start->json('conversation.id');

        $this->postJson("/api/chat/conversations/{$id}/transcript", [], $headers)->assertStatus(422);
        $this->postJson("/api/chat/conversations/{$id}/transcript", ['email' => 'me@example.com'], $headers)
            ->assertOk()
            ->assertJson(['sent' => true, 'email' => 'me@example.com']);

        Mail::assertQueued(TranscriptMail::class, fn ($mail) => $mail->hasTo('me@example.com'));
    }

    public function test_closing_sends_transcripts_when_configured(): void
    {
        Mail::fake();
        config([
            'chat-hybrid-ai.transcripts.on_close.visitor' => true,
            'chat-hybrid-ai.transcripts.on_close.recipients' => ['archive@example.com'],
        ]);
        $start = $this->startChat();

        $this->postJson("/api/chat/conversations/{$start->json('conversation.id')}/close", [], ['X-Chat-Token' => $start->json('token')])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        Mail::assertQueued(TranscriptMail::class, fn ($mail) => $mail->hasTo('vera@example.com') && $mail->hasTo('archive@example.com'));
    }

    public function test_transcript_mail_renders_the_conversation(): void
    {
        $this->startChat();

        $html = (new TranscriptMail(Conversation::first()))->render();

        $this->assertStringContainsString('Do you ship to Turkey?', $html);
        $this->assertStringContainsString('Hello from the bot', $html);
        $this->assertStringContainsString('Vera', $html);
    }

    public function test_without_ai_new_chats_wait_for_a_human(): void
    {
        config(['chat-hybrid-ai.ai.enabled' => false]);

        $this->startChat()->assertJsonPath('conversation.mode', 'waiting')->assertJsonCount(1, 'messages');
        $this->assertCount(0, $this->agent->calls);
    }
}
