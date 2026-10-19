<?php

namespace Siberfx\ChatHybridAi\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Siberfx\ChatHybridAi\ChatManager;
use Siberfx\ChatHybridAi\Enums\ConversationMode;
use Siberfx\ChatHybridAi\Enums\ConversationStatus;
use Siberfx\ChatHybridAi\Http\Resources\AdminConversationResource;
use Siberfx\ChatHybridAi\Http\Resources\MessageResource;
use Siberfx\ChatHybridAi\Models\Conversation;
use Siberfx\ChatHybridAi\Support\ChatModels;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin inbox: the page and its JSON API.
 */
class AdminController extends Controller
{
    public function __construct(protected ChatManager $chat) {}

    public function index(): View
    {
        return view('chat-hybrid-ai::admin.index', [
            'pollInterval' => (int) config('chat-hybrid-ai.widget.poll_interval', 3000),
            'aiEnabled' => $this->chat->aiEnabled(),
        ]);
    }

    /**
     * Settings a custom admin client needs.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'poll_interval' => (int) config('chat-hybrid-ai.widget.poll_interval', 3000),
            'ai' => $this->chat->aiEnabled(),
        ]);
    }

    /**
     * ?status=open|closed  ?mode=ai|waiting|human  ?mine=1  ?unread=1  ?search=
     */
    public function conversations(Request $request): AnonymousResourceCollection
    {
        $query = ChatModels::conversation()::query()
            ->with(['assignee', 'latestMessage'])
            ->orderByDesc('last_message_at');

        if ($status = ConversationStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status);
        }

        if ($mode = ConversationMode::tryFrom((string) $request->query('mode'))) {
            $query->where('mode', $mode);
        }

        if ($request->boolean('mine')) {
            $query->where('assigned_to', $request->user()->getKey());
        }

        if ($request->boolean('unread')) {
            $query->unreadByAdmin();
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                    ->orWhere('visitor_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('uuid', $search)
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$search}%"));
            });
        }

        return AdminConversationResource::collection(
            $query->paginate(min((int) $request->query('per_page', 30), 100))
        );
    }

    public function stats(): JsonResponse
    {
        $conversations = ChatModels::conversation();

        return response()->json([
            'open' => $conversations::open()->count(),
            'ai' => $conversations::open()->mode(ConversationMode::Ai)->count(),
            'waiting' => $conversations::open()->mode(ConversationMode::Waiting)->count(),
            'human' => $conversations::open()->mode(ConversationMode::Human)->count(),
            'unread' => $conversations::open()->unreadByAdmin()->count(),
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->forceFill(['admin_last_read_at' => now()])->save();

        return response()->json([
            'conversation' => new AdminConversationResource($conversation->load('assignee')),
            'messages' => MessageResource::collection(
                $conversation->messages()->with('sender')->orderBy('id')->get()
            ),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->with('sender')
            ->where('id', '>', (int) $request->query('after', 0))
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isNotEmpty()) {
            $conversation->forceFill(['admin_last_read_at' => now()])->save();
        }

        return response()->json([
            'conversation' => new AdminConversationResource($conversation->load('assignee')),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function send(Request $request, Conversation $conversation): MessageResource
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        return new MessageResource($this->chat->agentMessage($conversation, $request->user(), trim($data['body'])));
    }

    public function takeOver(Request $request, Conversation $conversation): AdminConversationResource
    {
        $this->chat->takeOver($conversation, $request->user());

        return new AdminConversationResource($conversation->load('assignee'));
    }

    public function returnToAi(Conversation $conversation): AdminConversationResource
    {
        abort_unless($this->chat->aiEnabled(), 422, __('The AI agent is disabled.'));

        $this->chat->returnToAi($conversation);

        return new AdminConversationResource($conversation->load('assignee'));
    }

    public function close(Conversation $conversation): AdminConversationResource
    {
        $this->chat->close($conversation);

        return new AdminConversationResource($conversation->load('assignee'));
    }

    public function reopen(Conversation $conversation): AdminConversationResource
    {
        $this->chat->reopen($conversation);

        return new AdminConversationResource($conversation->load('assignee'));
    }

    /**
     * Email the transcript to any addresses (defaults to the visitor).
     */
    public function transcript(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'emails' => ['nullable', 'array', 'max:10'],
            'emails.*' => ['email', 'max:190'],
        ]);

        $emails = $data['emails'] ?? array_filter([$conversation->visitor_email]);

        abort_if($emails === [], 422, __('No recipient: the visitor has no email address.'));

        $this->chat->sendTranscript($conversation, array_values($emails));

        return response()->json(['sent' => true, 'emails' => array_values($emails)]);
    }

    public function destroy(Conversation $conversation): Response
    {
        $conversation->delete();

        return response()->noContent();
    }
}
