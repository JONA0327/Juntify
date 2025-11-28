<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administrar Usuarios - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js',
        'resources/js/admin-users.js',
    ])
</head>
<body class="admin-users-page">
    <div class="particles" id="particles"></div>

    @include('partials.navbar')

    <div class="app-container">
        <main class="main-admin">
            <div class="content-header">
                <div>
                    <h1 class="page-title">Administrar usuarios</h1>
                    <p class="page-subtitle">Revisa roles, bloqueos y accesos a la plataforma</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='{{ route('admin.dashboard') }}'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                </div>
            </div>

            <div id="admin-users-alert" class="hidden"></div>

            <div class="info-card" style="padding: 0; overflow: hidden;">
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <tr>
                                <td colspan="5" class="text-center py-6 text-slate-400">Cargando usuarios...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="block-user-modal" class="modal hidden">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 17c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Bloquear usuario
                </h3>
                <button type="button" class="modal-close" data-close-block-modal>&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-4 text-slate-500">Antes de bloquear la cuenta necesitamos el motivo y la duración del bloqueo.</p>
                <form id="block-user-form">
                    <div class="form-group">
                        <label class="form-label" for="block-reason">Motivo del bloqueo</label>
                        <textarea id="block-reason" class="modal-input" rows="3" placeholder="Describe brevemente el motivo" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="block-duration">Duración</label>
                        <select id="block-duration" class="modal-input" required>
                            <option value="1_day">1 día</option>
                            <option value="1_week">1 semana</option>
                            <option value="1_month">1 mes</option>
                            <option value="permanent">Permanentemente</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-close-block-modal>Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="confirm-block-btn">Confirmar bloqueo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="delete-user-modal" class="modal hidden">
        <div class="modal-content" style="max-width: 480px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Eliminar usuario
                </h3>
                <button type="button" class="modal-close" data-close-delete-modal>&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-4 text-slate-500">
                    <strong>¿Eliminar definitivamente la cuenta de este usuario?</strong>
                </p>
                <p class="mb-4 text-slate-400 text-sm">
                    Esta acción no se puede deshacer. Todos los datos del usuario serán eliminados permanentemente del sistema.
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-close-delete-modal>Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-btn">Eliminar definitivamente</button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
