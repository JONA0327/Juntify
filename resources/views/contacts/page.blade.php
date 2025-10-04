@extends('layouts.app')

@push('styles')
    @vite(['resources/css/app.css'])
@endpush

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        @include('contacts.index')
    </div>
</div>
@vite(['resources/js/contacts.js'])
@endsection
