<?php

namespace Siberfx\ChatHybridAi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Siberfx\ChatHybridAi\ChatManager;
use Siberfx\ChatHybridAi\Http\Resources\ConversationResource;
use Siberfx\ChatHybridAi\Http\Resources\MessageResource;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Support\VisitorIdentity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API used by the chat bubble.
 */
class WidgetController extends Controller
{
    public function __construct(protected ChatManager $chat) {}

    public function script(): Response
    {
        return response()->file(__DIR__.'/../../../resources/js/chat-widget.js', [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function config(): JsonResponse
    {
        $widget = (array) config('chat-hybrid-ai.widget');

        return response()->json([
            'title' => $widget['title'] ?? null,
            'welcome' => $widget['welcome'] ?? null,
            'color' => $widget['color'] ?? '#4f46e5',
            'position' => $widget['position'] ?? 'right',
            'require_email' => (bool) ($widget['require_email'] ?? false),
            'poll_interval' => (int) ($widget['poll_interval'] ?? 3000),
            'max_message_length' => $this->maxLength(),
            'ai' => $this->chat->aiEnabled(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => [config('chat-hybrid-ai.widget.require_email') ? 'required' : 'nullable', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:'.$this->maxLength()],
            'meta' => ['nullable', 'array'],
            'meta.*' => ['nullable', 'string', 'max:500'],
            'identity' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation = $this->chat->start([
            'visitor_name' => $data['name'] ?? null,
            'visitor_email' => $data['email'] ?? null,
            'visitor_id' => VisitorIdentity::verify($data['identity'] ?? null),
            'subject' => $data['subject'] ?? null,
            'meta' => array_filter([
                ...($data['meta'] ?? []),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'page' => substr((string) $request->headers->get('referer'), 0, 500) ?: null,
            ]),
        ], $data['message'] ?? null);

        return response()->json([
            'token' => $conversation->token,
            'conversation' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($conversation->messages()->orderBy('id')->get()),
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json([
            'conversation' => new ConversationResource($conversation),
            'messages' => MessageResource::collection(
                $conversation->messages()->with('sender')->orderBy('id')->get()
            ),
        ]);
    }

    public function update(Request $request, Conversation $conversation): ConversationResource
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'identity' => ['nullable', 'string', 'max:500'],
        ]);

        $conversation->update(array_filter([
            'visitor_name' => $data['name'] ?? null,
            'visitor_email' => $data['email'] ?? null,
            // A guest who signs in mid-chat; an identified chat never changes hands.
            'visitor_id' => $conversation->visitor_id === null ? VisitorIdentity::verify($data['identity'] ?? null) : null,
        ]));

        return new ConversationResource($conversation);
    }

    /**
     * Poll for new messages: ?after={last message id}.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->with('sender')
            ->where('id', '>', (int) $request->query('after', 0))
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'conversation' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function send(Request $request, Conversation $conversation): MessageResource
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.$this->maxLength()],
        ]);

        return new MessageResource($this->chat->visitorMessage($conversation, trim($data['body'])));
    }

    public function read(Conversation $conversation): Response
    {
        $conversation->forceFill(['visitor_last_read_at' => now()])->save();

        return response()->noContent();
    }

    public function human(Conversation $conversation): ConversationResource
    {
        $this->chat->requestHuman($conversation, 'visitor');

        return new ConversationResource($conversation);
    }

    public function close(Conversation $conversation): ConversationResource
    {
        $this->chat->close($conversation);

        return new ConversationResource($conversation);
    }

    /**
     * Email the transcript to the visitor. Only to the address on the
     * conversation, so the endpoint can't be used to send mail anywhere.
     */
    public function transcript(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['email' => ['nullable', 'email', 'max:190']]);

        if (! empty($data['email'])) {
            $conversation->update(['visitor_email' => $data['email']]);
        }

        abort_unless($conversation->visitor_email, 422, __('Please provide an email address.'));

        $this->chat->sendTranscript($conversation, $conversation->visitor_email);

        return response()->json(['sent' => true, 'email' => $conversation->visitor_email]);
    }

    protected function maxLength(): int
    {
        return (int) config('chat-hybrid-ai.widget.max_message_length', 4000);
    }
}
