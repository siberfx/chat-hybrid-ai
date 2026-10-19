<?php

namespace Siberfx\ChatHybridAi\View\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Siberfx\ChatHybridAi\Support\VisitorIdentity;

/**
 * <x-chat-hybrid-ai-bubble /> — drops the chat bubble on the page.
 * Pass name/email to prefill a logged-in visitor, any extra key/value
 * context as :meta, and :identify="auth()->user()" so the AI's tools can
 * look up that user's own records.
 */
class Bubble extends Component
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public array $meta = [],
        public ?string $color = null,
        public ?string $title = null,
        public Model|int|string|null $identify = null,
    ) {}

    /** Signed id of the logged-in user, so the AI's tools can trust it. */
    public function identity(): ?string
    {
        return $this->identify === null ? null : VisitorIdentity::sign($this->identify);
    }

    public function render()
    {
        return view('chat-hybrid-ai::components.bubble');
    }
}
