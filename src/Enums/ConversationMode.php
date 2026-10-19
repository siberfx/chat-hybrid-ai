<?php

namespace Siberfx\ChatHybridAi\Enums;

enum ConversationMode: string
{
    /** The AI agent answers visitor messages. */
    case Ai = 'ai';

    /** A human has been requested; nobody has picked it up yet. */
    case Waiting = 'waiting';

    /** A human agent is handling the conversation. */
    case Human = 'human';
}
