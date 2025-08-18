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

                    <!-- Columna Izquierda: Calendario y Pestañas -->
                    <div class="lg:col-span-2 flex flex-col gap-8">

                        <!-- 2. Calendario Principal Mensual -->
                        @include('tasks.partials._calendar-main')

                        <!-- 3. Pestañas de Tareas -->
                        @include('tasks.partials._tabs-tareas')

                    </div>

                    <!-- Columna Derecha: Detalles de la Tarea -->
                    <aside class="col-span-1">
                        <!-- 4. Sidebar con mini-calendario y detalles -->
                        @include('tasks.partials._sidebar-details')
                    </aside>

                </div>
            </div>
        </main>
    </div>

    <!-- Modal para crear/editar tareas -->
    @include('tasks.partials._modal-task')

    <!-- Scripts -->
    <script>
        // Datos para JavaScript
        window.taskData = {
            apiTasks: '{{ route("api.tasks") }}',
            apiTasksStore: '{{ route("api.tasks.store") }}',
            stats: @json($stats),
            csrfToken: '{{ csrf_token() }}'
        };
    </script>
</body>
</html>
