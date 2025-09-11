<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Tareas - Juntify</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css',
        'resources/css/contacts/index.css',
        'resources/css/audio-processing.css'
    ])

    <!-- Styles específicos para tareas -->
    <style>
        .task-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(100, 116, 139, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .task-card:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(100, 116, 139, 0.4);
        }

        .priority-alta {
            border-left: 4px solid #ef4444;
        }

        .priority-media {
            border-left: 4px solid #f59e0b;
        }

        .priority-baja {
            border-left: 4px solid #10b981;
        }

        .task-completed {
            opacity: 0.6;
        }

        .task-overdue {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pl-24 pt-24" style="margin-top:130px;">
            <!-- Contenedor Centrado -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- 1. Encabezado de la página -->
                @include('tasks.partials._header')

                <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <!-- Columna Izquierda: Calendario + Reuniones con pestañas -->
                    <div class="lg:col-span-2 flex flex-col gap-8">
                        @include('tasks.partials._calendar-main')
                        @include('tasks.partials._tabs-reuniones')
                    </div>

                    <!-- Columna Derecha: Lista de tareas (tasks_laravel) -->
                    <aside class="col-span-1">
                        @include('tasks.partials._sidebar-details')

                        <!-- Placeholder (visible hasta seleccionar reunión) -->
                        <div id="tasks-empty" class="info-card p-6 mt-8 text-center text-slate-300">
                            <div class="text-xl font-semibold mb-2">Tareas de la reunión</div>
                            <div class="text-blue-400">Selecciona una conversación</div>
                        </div>

                        <!-- Panel de Tareas (oculto hasta que se seleccione una reunión) -->
                        <div id="tasks-panel" class="info-card p-4 mt-8 hidden">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="card-title">Tareas</h3>
                                <button onclick="openTaskModal()" class="btn btn-primary">+ Agregar tarea</button>
                            </div>
                            <div id="tasks-sidebar-stats" class="grid grid-cols-2 gap-2 mb-4 text-sm">
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-total" class="text-lg font-bold text-blue-400">0</div><div class="text-slate-400">Total</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-pending" class="text-lg font-bold text-yellow-400">0</div><div class="text-slate-400">Pendientes</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-inprogress" class="text-lg font-bold text-orange-400">0</div><div class="text-slate-400">En progreso</div></div>
                                <div class="bg-slate-800 rounded p-3 text-center"><div id="stat-completed" class="text-lg font-bold text-green-400">0</div><div class="text-slate-400">Completadas</div></div>
                            </div>
                            <div id="tasks-sidebar-list" class="flex flex-col gap-3"></div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para crear/editar tareas -->
    @include('tasks.partials._modal-task')

    <!-- Modal de detalles de tareas -->
    @include('tasks.partials._task-details-modal')

    <!-- Scripts -->
    <script>
        // Endpoints para tasks_laravel
        window.taskLaravel = {
            apiMeetings: '{{ route("api.tasks-laravel.meetings", [], false) }}',
            apiImport: (id) => `/api/tasks-laravel/import/${id}`,
            apiExists: '{{ route("api.tasks-laravel.exists", [], false) }}',
            apiTasks: '{{ route("api.tasks-laravel.tasks", [], false) }}',
            csrf: '{{ csrf_token() }}'
        };
        // Calendario: usar events de tasks_laravel
        window.taskData = {
            apiTasks: '{{ route("api.tasks-laravel.calendar", [], false) }}'
        };

        // Utilidad para mostrar/ocultar panel de tareas
        window.showTasksPanel = function(show = true) {
            const panel = document.getElementById('tasks-panel');
            const empty = document.getElementById('tasks-empty');
            if (show) {
                panel.classList.remove('hidden');
                empty.classList.add('hidden');
            } else {
                panel.classList.add('hidden');
                empty.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
