<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Global Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('head')
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full lg:pl-24 pt-20 lg:pt-24">
            @yield('content')
        </main>
    </div>

    @yield('modals')
    @yield('scripts')
</body>
</html>
