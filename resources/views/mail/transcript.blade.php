<x-mail::message>
# {{ __('Conversation transcript') }}

{{ __('Started') }}: {{ $conversation->created_at->toDayDateTimeString() }}
@if($conversation->subject)
<br>{{ __('Subject') }}: {{ $conversation->subject }}
@endif

<x-mail::panel>
@foreach($messages as $message)
**{{ $message->senderName() }}** <small>{{ $message->created_at->format('H:i') }}</small><br>
{!! nl2br(e($message->body)) !!}

@endforeach
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
