<x-mail::message>
# {{ __('A visitor is waiting for a human') }}

**{{ $conversation->visitor_name ?: __('Visitor') }}**@if($conversation->visitor_email) ({{ $conversation->visitor_email }})@endif

{{ __('Reason') }}: {{ match($reason) {
    'visitor' => __('the visitor asked for a person'),
    'ai' => __('the assistant handed the conversation over'),
    'refusal' => __('the assistant declined to answer'),
    'ai_error' => __('the assistant is unavailable'),
    default => $reason,
} }}

<x-mail::panel>
@foreach($latest as $message)
**{{ $message->senderName() }}:** {{ \Illuminate\Support\Str::limit($message->body, 300) }}

@endforeach
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('Open the conversation') }}
</x-mail::button>
</x-mail::message>
