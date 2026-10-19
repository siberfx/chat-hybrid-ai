<script
    src="{{ route('chat-hybrid-ai.widget.script') }}"
    data-api="{{ url(config('chat-hybrid-ai.routes.widget.prefix')) }}"
    @if($name) data-name="{{ $name }}" @endif
    @if($email) data-email="{{ $email }}" @endif
    @if($color) data-color="{{ $color }}" @endif
    @if($title) data-title="{{ $title }}" @endif
    @if($meta) data-meta="{{ json_encode($meta) }}" @endif
    defer
></script>
