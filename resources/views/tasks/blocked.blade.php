<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tareas Bloqueadas - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/profile.css',
        'resources/css/new-meeting.css',
        'resources/css/index.css',
        'resources/css/reuniones_v2.css'
    ])
</head>
<body class="bg-slate-950 text-slate-200 font-sans antialiased">

    @include('partials.global-vars')

    <div class="flex">

        @include('partials.navbar')
        @include('partials.mobile-nav')

        <main class="w-full pt-20 lg:pl-24 lg:pt-24 lg:mt-[130px]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-4xl mx-auto text-center">
                    <div class="bg-slate-800/50 rounded-lg p-8">
                        <svg class="w-16 h-16 mx-auto text-yellow-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>

                        <h1 class="text-2xl font-bold mb-4">Tareas bloqueadas para tu plan actual</h1>

                        <p class="text-slate-400 mb-6">
                            Las tareas están disponibles para los planes: <strong class="text-white">Negocios</strong> y <strong class="text-white">Enterprise</strong>.
                        </p>

                        <p class="text-slate-400 mb-8">
                            Debes actualizar tu plan para acceder a esta funcionalidad.
                        </p>

                        <div class="flex gap-4 justify-center">
                            <a href="{{ route('reuniones.index') }}" class="btn bg-slate-700 hover:bg-slate-600 text-white px-6 py-3 rounded-lg transition-colors">
                                Volver a Reuniones
                            </a>
                            <button onclick="showTasksLockedModal()" class="btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors">
                                Ver Planes Disponibles
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Upgrade -->
    <div class="modal" id="postpone-locked-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="modal-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Tareas bloqueadas
                </h2>
            </div>
            <div class="modal-body">
                <p class="modal-description">
                    Las tareas están disponibles para los planes: <strong>Negocios</strong> y <strong>Enterprise</strong>.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('postpone-locked-modal')" class="btn">Cerrar</button>
                <button onclick="window.open('/planes', '_blank')" class="btn btn-primary">Cambiar plan</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        // Mostrar modal automáticamente al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            showTasksLockedModal();
        });
    </script>

</body>
</html>
