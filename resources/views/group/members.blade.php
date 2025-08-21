<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Miembros del Grupo</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
<div class="max-w-4xl mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Miembros de {{ $group->nombre_grupo }}</h1>
    <ul>
        @foreach($group->users as $user)
            <li class="mb-2">
                <span class="mr-2">{{ $user->full_name ?? $user->email }}</span>
                <form method="POST" action="{{ route('api.groups.members.update', [$group->id, $user->id]) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <select name="rol" onchange="this.form.submit()" class="border rounded p-1">
                        <option value="meeting_viewer" {{ $user->pivot->rol === 'meeting_viewer' ? 'selected' : '' }}>meeting_viewer</option>
                        <option value="full_meeting_access" {{ $user->pivot->rol === 'full_meeting_access' ? 'selected' : '' }}>full_meeting_access</option>
                    </select>
                </form>
            </li>
        @endforeach
    </ul>
</div>
</body>
</html>
