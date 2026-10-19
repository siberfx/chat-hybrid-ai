<?php

namespace Siberfx\ChatHybridAi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Siberfx\ChatHybridAi\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visitors prove they own a conversation with the token they got when it started.
 */
class VerifyConversationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = $request->route('conversation');
        $token = (string) $request->header('X-Chat-Token', '');

        if (! $conversation instanceof Conversation || $token === '' || ! hash_equals($conversation->token, $token)) {
            abort(404);
        }

        return $next($request);
    }
}
