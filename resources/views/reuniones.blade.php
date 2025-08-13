<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Reuniones - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js', 'resources/css/new-meeting.css','resources/css/index.css',
        'resources/css/reuniones_v2.css', /* Nuevo archivo de estilos */
        'resources/js/reuniones_v2.js'   /* Nuevo archivo de script */
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <header class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 pb-12 fade-in">
                    <!-- Columna Izquierda: Título y Filtros -->
                    <div class="flex-1 space-y-6">
                        <div class="space-y-3">
                            <h1 class="text-4xl font-bold text-white tracking-tight">Reuniones</h1>
                            <p class="text-slate-400 text-lg">Gestiona y organiza todas tus reuniones de forma eficiente</p>
                        </div>

                        <!-- Barra de Búsqueda y Filtros -->
                        <div class="flex flex-col sm:flex-row gap-4">
                            <!-- Barra de Búsqueda -->
                            <div class="relative flex-1 max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                                </div>
                                <input type="text" placeholder="Buscar en reuniones..." class="bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400/50 transition-all duration-200 shadow-lg shadow-black/10">
                            </div>

                            <!-- Botón de Filtro de Fecha -->
                            <button class="bg-slate-800/50 backdrop-blur-custom text-slate-200 px-4 py-3 rounded-xl flex items-center gap-3 hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 border border-slate-700/50 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span class="font-medium">Fecha</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Columna Derecha: Análisis y Botones de Acción -->
                    <div class="flex flex-col items-end gap-6 fade-in stagger-1">
                        <!-- Análisis Restantes -->
                        <div class="w-full sm:w-80 bg-gradient-to-br from-slate-800/50 to-slate-800/30 backdrop-blur-custom border border-slate-700/50 rounded-xl p-5 shadow-lg shadow-black/10">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-sm font-semibold text-slate-300">Análisis mensuales</p>
                                <span class="text-xs text-slate-400 bg-slate-700/50 px-2 py-1 rounded-full">45/100</span>
                            </div>
                            <div class="w-full bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-yellow-400 to-yellow-500 h-2.5 rounded-full shadow-lg shadow-yellow-400/25" style="width: 45%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">55 análisis restantes este mes</p>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center gap-3">
                            <button class="inline-flex items-center gap-3 px-5 py-3 bg-slate-800/50 backdrop-blur-custom border border-slate-700/50 rounded-xl text-slate-200 font-medium hover:bg-slate-700/50 hover:border-slate-600/50 transition-all duration-200 group shadow-lg shadow-black/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-slate-300 transition-colors duration-200" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                <span>Nuevo Contenedor</span>
                            </button>

                            <button class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 text-slate-900 font-semibold rounded-xl hover:from-yellow-500 hover:to-yellow-600 transition-all duration-200 shadow-lg shadow-yellow-400/25 hover:shadow-yellow-400/40 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                <span>Reuniones Pendientes</span>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Sistema de Reuniones -->
                <div class="fade-in stagger-2">
                    {{-- El contenido de las reuniones se cargará dinámicamente aquí --}}
                    <div class="loading-card">
                        <div class="loading-spinner"></div>
                        <p>Cargando reuniones...</p>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
