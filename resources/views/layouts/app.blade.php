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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/mobile-navigation.css'])
    @stack('styles')
    @yield('head')
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">
    <div class="flex">
        @include('partials.navbar')

        <main class="main-content w-full lg:pl-24 pt-20 lg:pt-24">
            @yield('content')
        </main>
    </div>

    <!-- Nueva navegación móvil mejorada -->
    @include('partials.mobile-bottom-nav')

    <!-- Navegación móvil de prueba -->
    <div id="mobileNavTest" style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(20px);
        border-top: 1px solid rgba(59, 130, 246, 0.2);
        padding: 12px 16px;
        z-index: 9999;
        display: grid;
        grid-template-columns: 1fr 1fr 80px 1fr 1fr;
        align-items: center;
        gap: 8px;
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.3);
    ">
        <!-- Reuniones -->
        <div onclick="window.location.href='{{ route('reuniones.index') }}'" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            color: #94a3b8;
        ">
            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span style="font-size: 11px; font-weight: 500;">Reuniones</span>
        </div>

        <!-- Tareas -->
        <div onclick="window.location.href='{{ route('tasks.index') }}'" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            color: #94a3b8;
        ">
            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span style="font-size: 11px; font-weight: 500;">Tareas</span>
        </div>

        <!-- Nueva Reunión -->
        <div onclick="window.location.href='{{ route('reuniones.create') }}'" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        ">
            <div style="
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
            ">
                <svg style="width: 24px; height: 24px; color: white;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <span style="font-size: 11px; font-weight: 600; color: #94a3b8; margin-top: 4px;">Nueva</span>
        </div>

        <!-- Asistente -->
        <div onclick="window.location.href='{{ route('ai-assistant.index') }}'" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            color: #94a3b8;
        ">
            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.091z" />
            </svg>
            <span style="font-size: 11px; font-weight: 500;">Asistente</span>
        </div>

        <!-- Más -->
        <div style="
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px;
            border-radius: 12px;
            cursor: pointer;
            color: #94a3b8;
        ">
            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <span style="font-size: 11px; font-weight: 500;">Más</span>
        </div>
    </div>

    <script>
        // Mostrar solo en móviles
        document.addEventListener('DOMContentLoaded', function() {
            function toggleNavTest() {
                const nav = document.getElementById('mobileNavTest');
                if (nav) {
                    if (window.innerWidth <= 768) {
                        nav.style.display = 'grid';
                        document.body.style.paddingBottom = '90px';
                    } else {
                        nav.style.display = 'none';
                        document.body.style.paddingBottom = '';
                    }
                }
            }
            toggleNavTest();
            window.addEventListener('resize', toggleNavTest);
        });
    </script>

    @yield('modals')

    @yield('scripts')
    @stack('scripts')
</body>
</html>
