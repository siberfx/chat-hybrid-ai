<?php

namespace Siberfx\ChatHybridAi\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lets the agent hand the chat to a person. The driver moves the conversation
 * to "waiting" after the reply.
 */
class RequestHuman implements Tool
{
    public bool $requested = false;

    public function name(): string
    {
        return 'request_human';
    }

    public function description(): string
    {
        return 'Hand the conversation to a human team member. Use it when you cannot help, the visitor asks '
            .'for a person, or the request needs a human (billing disputes, account access, complaints). '
            .'Afterwards, tell the visitor that a team member will join shortly.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->description('Why a person is needed, for the team.'),
        ];
    }

    public function handle(Request $request): string
    {
        $this->requested = true;

        return 'A team member has been notified and will join the conversation.';
    }
}
