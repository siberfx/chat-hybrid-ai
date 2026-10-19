<?php

namespace Siberfx\ChatHybridAi\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Siberfx\ChatHybridAi\Models\Conversation;

class HandoffRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Conversation $conversation, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Chat needs a human: :name', ['name' => $this->conversation->visitor_name ?: __('Visitor')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'chat-hybrid-ai::mail.handoff',
            with: [
                'conversation' => $this->conversation,
                'reason' => $this->reason,
                'url' => route('chat-hybrid-ai.admin.index').'#'.$this->conversation->uuid,
                'latest' => $this->conversation->messages()->latest('id')->limit(5)->get()->reverse(),
            ],
        );
    }
}
