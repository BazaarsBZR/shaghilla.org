@props([
    'name',
    'class' => 'h-4 w-4',
    'title' => null,
])

@php
    $icon = (string) ($name ?? '');
    $title = $title !== null ? (string) $title : null;
@endphp

@if ($icon === 'fa-whatsapp')
    <svg viewBox="0 0 24 24" fill="currentColor" @class([$class]) aria-hidden="{{ $title ? 'false' : 'true' }}" @if($title) role="img" @endif>
        @if($title)<title>{{ $title }}</title>@endif
        <path d="M12 2a9.9 9.9 0 0 0-8.5 15l-1 3.7a1 1 0 0 0 1.2 1.2l3.7-1A9.9 9.9 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.3.6.6-2.3-.2-.3A8 8 0 1 1 12 20Z"/>
        <path d="M16.8 14.6c-.2-.1-1.3-.6-1.5-.7-.2-.1-.4-.1-.6.1-.2.2-.7.7-.8.9-.2.2-.3.2-.5.1-1-.5-1.9-1.1-2.6-2-.2-.2-.1-.4 0-.5l.5-.6c.1-.2.1-.4 0-.6-.1-.2-.5-1.3-.7-1.5-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9 0 1.1.8 2.1.9 2.2.1.1 1.6 2.5 4 3.5.6.2 1 .4 1.4.5.6.2 1.1.2 1.5.1.5-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1 0-.1-.2-.2-.4-.3Z"/>
    </svg>
@elseif ($icon === 'fa-x-twitter')
    <svg viewBox="0 0 24 24" fill="currentColor" @class([$class]) aria-hidden="{{ $title ? 'false' : 'true' }}" @if($title) role="img" @endif>
        @if($title)<title>{{ $title }}</title>@endif
        <path d="M18.9 3H21l-6.7 7.7L22 21h-6.2l-4.9-6.3L5.6 21H3.5l7.2-8.3L3 3h6.3l4.4 5.7L18.9 3Zm-2.2 16h1.7L7.2 4.9H5.4L16.7 19Z"/>
    </svg>
@elseif (in_array($icon, ['fa-facebook-f', 'fa-facebook'], true))
    <svg viewBox="0 0 24 24" fill="currentColor" @class([$class]) aria-hidden="{{ $title ? 'false' : 'true' }}" @if($title) role="img" @endif>
        @if($title)<title>{{ $title }}</title>@endif
        <path d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.3-1.6 1.6-1.6h1.7V4.8c-.3 0-1.4-.1-2.6-.1-2.6 0-4.3 1.6-4.3 4.5V11H7.5v3h2.4v8h3.6Z"/>
    </svg>
@elseif ($icon === 'fa-instagram')
    <svg viewBox="0 0 24 24" fill="currentColor" @class([$class]) aria-hidden="{{ $title ? 'false' : 'true' }}" @if($title) role="img" @endif>
        @if($title)<title>{{ $title }}</title>@endif
        <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3Z"/>
        <path d="M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
        <path d="M17.5 6.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/>
    </svg>
@else
    <svg viewBox="0 0 24 24" fill="currentColor" @class([$class]) aria-hidden="{{ $title ? 'false' : 'true' }}" @if($title) role="img" @endif>
        @if($title)<title>{{ $title }}</title>@endif
        <path d="M14 9a3 3 0 1 0-4 0v.4a6 6 0 0 0-3 5.2V17a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2.4a6 6 0 0 0-3-5.2V9Z"/>
    </svg>
@endif
