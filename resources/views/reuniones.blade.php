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
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/new-meeting.css','resources/css/index.css'])

</head>
<body class="bg-slate-900 text-slate-200 font-sans">

    <div class="flex">
        
        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Header -->
                <header class="flex flex-wrap justify-between items-start gap-8 pb-8">
                    <!-- Columna Izquierda: Título y Filtros -->
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-white">Reuniones</h1>
                        <div class="flex items-center gap-4 mt-4">
                           <!-- Barra de Búsqueda -->
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" placeholder="Buscar en reuniones..." class="bg-slate-800 border border-slate-700 rounded-lg py-2 pl-10 pr-4 block w-full text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-transparent">
                            </div>
                            <!-- Botón de Filtro de Fecha -->
                            <button class="bg-slate-800 text-slate-200 px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-slate-700 transition-colors border border-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span>Fecha</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Columna Derecha: Análisis y Botones de Acción -->
                    <div class="flex flex-col items-end gap-4">
                         <!-- Análisis Restantes -->
                        <div class="bg-slate-800 border border-slate-700 rounded-lg p-3 w-64">
                            <p class="text-xs font-medium text-slate-400">Análisis mensuales restantes</p>
                            <div class="w-full bg-slate-600 rounded-full h-1.5 mt-2">
                              <div class="bg-yellow-400 h-1.5 rounded-full" style="width: 45%"></div>
                            </div>
                        </div>
                        <!-- Botones de Acción -->
                        <div class="flex items-center gap-4">
                            <button class="bg-slate-800 text-slate-200 font-bold px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-slate-700 transition-colors border border-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                Nuevo Contenedor
                            </button>
                            <button class="bg-yellow-400 text-slate-900 font-bold px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-yellow-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                Nueva Reunión
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Sistema de Pestañas -->
                <div>
                    <div class="border-b border-slate-700">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button onclick="changeTab('my-meetings')" id="tab-my-meetings" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-yellow-400 border-yellow-400">
                                Mis Reuniones
                            </button>
                            <button onclick="changeTab('shared-meetings')" id="tab-shared-meetings" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-slate-400 border-transparent hover:text-slate-200 hover:border-slate-300">
                                Compartidas Conmigo
                            </button>
                        </nav>
                    </div>

                    <!-- Filtros de Ordenamiento -->
                    <div class="py-4 flex justify-end">
                         <select class="bg-slate-800 border border-slate-700 rounded-lg py-2 px-3 text-slate-200 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                            <option>Ordenar por fecha</option>
                            <option>Ordenar por nombre</option>
                        </select>
                    </div>

                    <div class="py-6">
                        <!-- Panel para "Mis Reuniones" -->
                        <div id="panel-my-meetings" class="tab-panel">
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6">
                                
                                <!-- EJEMPLO DE UN CONTENEDOR -->
                                <a href="#" class="bg-slate-800 border-2 border-dashed border-slate-700 rounded-xl p-6 flex flex-col gap-4 hover:border-yellow-400 transition-colors group">
                                    <div class="flex justify-between items-start">
                                        <h2 class="font-semibold text-lg text-slate-50 group-hover:text-yellow-400">Proyecto Juntify</h2>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-500 group-hover:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                    </div>
                                    <div class="flex flex-col gap-2 text-sm text-slate-400">
                                        <span>Contiene 8 reuniones</span>
                                    </div>
                                </a>
                                <!-- FIN DEL EJEMPLO DE CONTENEDOR -->

                                <!-- EJEMPLO DE REUNIÓN INDIVIDUAL -->
                                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col gap-4">
                                    <h2 class="font-semibold text-lg text-slate-50">Reunión de Equipo Semanal</h2>
                                    <!-- ... más detalles de la tarjeta ... -->
                                </div>
                                 <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col gap-4">
                                    <h2 class="font-semibold text-lg text-slate-50">Llamada Rápida Cliente</h2>
                                    <!-- ... más detalles de la tarjeta ... -->
                                </div>

                            </div>
                        </div>

                        <!-- Panel para "Compartidas Conmigo" -->
                        <div id="panel-shared-meetings" class="tab-panel hidden">
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-6">
                                <!-- Aquí mostrarías contenedores y reuniones compartidas, con la misma lógica -->
                                <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 flex flex-col gap-4">
                                    <h2 class="font-semibold text-lg text-slate-50">Contenido de la pestaña "Compartidas"</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function changeTab(tabId) {
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('text-yellow-400', 'border-yellow-400');
                button.classList.add('text-slate-400', 'border-transparent');
            });
            document.getElementById(`panel-${tabId}`).classList.remove('hidden');
            const activeButton = document.getElementById(`tab-${tabId}`);
            activeButton.classList.add('text-yellow-400', 'border-yellow-400');
            activeButton.classList.remove('text-slate-400', 'border-transparent');
        }
    </script>

</body>
</html>
