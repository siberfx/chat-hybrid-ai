<?php

namespace Siberfx\ChatHybridAi\Enums;

enum SenderType: string
{
    case Visitor = 'visitor';
    case Agent = 'agent';
    case Ai = 'ai';
    case System = 'system';
}
