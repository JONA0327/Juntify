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

    <div class="mobile-bottom-nav">
        <div class="nav-item" onclick="window.location.href='{{ route('dashboard') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span class="nav-label">Inicio</span>
        </div>
        <div class="nav-item" onclick="window.location.href='{{ route('profile.show') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9A3.75 3.75 0 1112 5.25 3.75 3.75 0 0115.75 9zM18 21H6a2.25 2.25 0 01-2.25-2.25v-1.5a2.25 2.25 0 012.25-2.25h12a2.25 2.25 0 012.25 2.25v1.5A2.25 2.25 0 0118 21z" />
            </svg>
            <span class="nav-label">Perfil</span>
        </div>
        <div class="nav-item" onclick="window.location.href='{{ route('admin.dashboard') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="nav-label">Admin</span>
        </div>
    </div>

    <div class="mobile-dropdown-overlay" id="mobile-dropdown-overlay" onclick="closeMobileDropdown()"></div>

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
