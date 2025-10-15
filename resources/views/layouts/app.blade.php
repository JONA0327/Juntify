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

    @php
        $inlineFavicon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="#0f172a"/><path fill="#38bdf8" d="M20 12h8v24a8 8 0 0 0 16 0h8c0 13.255-10.745 24-24 24S4 49.255 4 36V12h8v24c0 8.837 7.163 16 16 16s16-7.163 16-16V12h8v24a24 24 0 0 1-48 0z"/></svg>');
    @endphp
    <link rel="icon" type="image/svg+xml" href="{{ $inlineFavicon }}">

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
