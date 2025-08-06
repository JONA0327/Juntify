<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Conversaciones - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- =================================================================
    START: Carga de assets con Vite
    Esta directiva carga los archivos CSS y JS compilados por Vite.
    Asegúrate de que Tailwind CSS esté importado en tu resources/css/app.css
    ================================================================== -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/new-meeting.css','resources/css/index.css'])    <!-- END: Carga de assets con Vite -->

</head>
<body class="bg-slate-900 text-slate-200 font-sans">

    <div class="flex">
        
        <!-- =================================================================
        START: Inclusión de las barras de navegación de Blade
        ================================================================== -->
        @include('partials.navbar')
        @include('partials.mobile-nav')
        <!-- END: Inclusión de las barras de navegación de Blade -->

        <!-- =================================================================
        START: Contenido Principal
        Añadimos `pl-24` para la barra lateral y `pt-16` para la barra superior.
        Ajusta estos valores según el tamaño de tus navbars.
        ================================================================== -->
        <main class="w-full pl-24 pt-16">
            <!-- Contenedor Centrado -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center gap-4 pb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-white">Mis Conversaciones</h1>
                        <div class="relative mt-4">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="text" placeholder="Buscar conversaciones..." class="bg-slate-800 border border-slate-700 rounded-lg py-2 pl-10 pr-4 block w-full max-w-md text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent">
                        </div>
                    </div>
                    <button class="bg-slate-800 border border-slate-700 text-slate-200 px-6 py-3 rounded-lg font-medium whitespace-nowrap">
                        Análisis mensuales restantes
                    </button>
                </header>

                <!-- Cuadrícula de Conversaciones -->
                <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6">
                    
                    <!-- Ejemplo de tarjeta de conversación 1 -->
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col gap-4 transition-all hover:-translate-y-1 hover:shadow-lg hover:border-slate-600 cursor-pointer">
                        <div class="flex justify-between items-start">
                            <h2 class="font-semibold text-lg text-slate-50">Fernanda CEP 2</h2>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 hover:text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                                <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h6a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                        <div class="flex flex-col gap-2 text-sm text-slate-400">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                                    31 jul 2025
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.414-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>
                                    2:10
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" /></svg>
                                1 participante
                            </div>
                        </div>
                    </div>

                    <!-- Ejemplo de tarjeta de conversación 2 -->
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col gap-4 transition-all hover:-translate-y-1 hover:shadow-lg hover:border-slate-600 cursor-pointer">
                        <div class="flex justify-between items-start">
                            <h2 class="font-semibold text-lg text-slate-50">Kualifin 6</h2>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 hover:text-white" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                                <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h6a2 2 0 00-2-2H5z" />
                            </svg>
                        </div>
                        <div class="flex flex-col gap-2 text-sm text-slate-400">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                                    23 jul 2025
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.414-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>
                                    80:08
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" /></svg>
                                5 participantes
                            </div>
                        </div>
                    </div>

                    <!-- Agrega más tarjetas aquí si es necesario -->

                </div>
            </div>
        </main>
        <!-- END: Contenido Principal -->
    </div>

    <!-- Botón de Acción Flotante (FAB) -->
    <div class="fixed bottom-8 right-8 w-16 h-16 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg cursor-pointer hover:bg-yellow-300 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-900" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.28 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
    </div>

</body>
</html>
