@extends('layouts.app')

@section('content')
    @vite(['resources/css/assistant.css', 'resources/js/assistant.js'])
    <div class="assistant-container">
        @include('assistant.partials.chat-history')
        @include('assistant.partials.message-panel')
        @include('assistant.partials.meetings')
    </div>
@endsection
