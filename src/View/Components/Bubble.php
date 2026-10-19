<?php

namespace Siberfx\ChatHybridAi\View\Components;

use Illuminate\View\Component;

/**
 * <x-chat-hybrid-ai-bubble /> — drops the chat bubble on the page.
 * Pass name/email to prefill a logged-in visitor, and any extra
 * key/value context as :meta.
 */
class Bubble extends Component
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public array $meta = [],
        public ?string $color = null,
        public ?string $title = null,
    ) {}

    public function render()
    {
        return view('chat-hybrid-ai::components.bubble');
    }
}
