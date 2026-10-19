<?php

namespace Siberfx\ChatHybridAi\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Siberfx\ChatHybridAi\Enums\SenderType;
use Siberfx\ChatHybridAi\Models\Conversation;

class TranscriptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your conversation with :app', ['app' => config('app.name')])
                .($this->conversation->subject ? ' - '.$this->conversation->subject : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'chat-hybrid-ai::mail.transcript',
            with: [
                'conversation' => $this->conversation,
                'messages' => $this->conversation->messages()
                    ->with('sender')
                    ->where('sender_type', '!=', SenderType::System)
                    ->orderBy('id')
                    ->get(),
            ],
        );
    }
}
