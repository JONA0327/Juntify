<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administrar Paneles - Juntify</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/index.css',
        'resources/css/profile.css',
        'resources/js/profile.js',
        'resources/js/admin-panels.js',
    ])
</head>
<body class="admin-panels-page">
    <div class="particles" id="particles"></div>

    @include('partials.navbar')

    <div class="mobile-bottom-nav">
        <div class="nav-item" onclick="window.location.href='{{ route('dashboard') }}'">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v1.5M17.25 3v1.5M3.75 7.5h16.5M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v12A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V6.75z" />
            </svg>
            <span class="nav-label">Inicio</span>
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
                    <h1 class="page-title">Administrar paneles</h1>
                    <p class="page-subtitle">Da de alta empresas y asigna administradores dedicados</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" onclick="window.location.href='{{ route('admin.dashboard') }}'">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver al panel
                    </button>
                    <button class="btn btn-primary" id="create-panel-btn">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Crear nuevo panel
                    </button>
                </div>
            </div>

            <div id="admin-panels-alert" class="hidden"></div>

            <div class="info-card" style="padding: 0; overflow: hidden;">
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Administrador</th>
                                <th>Email</th>
                                <th>Rol actual</th>
                                <th>URL del panel</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody id="panels-table-body">
                            <tr>
                                <td colspan="6" class="text-center py-6 text-slate-400">Cargando paneles...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="create-panel-modal" class="modal hidden">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <svg class="modal-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nuevo panel empresarial
                </h3>
                <button type="button" class="modal-close" data-close-panel-modal>&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-panel-form">
                    <div class="form-group">
                        <label class="form-label" for="company-name">Nombre de la empresa</label>
                        <input id="company-name" type="text" class="modal-input" placeholder="Ingresa el nombre legal o comercial" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="panel-admin">Administrador</label>
                        <select id="panel-admin" class="modal-input" required>
                            <option value="" disabled selected>Selecciona un usuario</option>
                        </select>
                        <small class="input-hint">Solo usuarios con roles Enterprise, Founder o Developer.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="panel-url">URL del panel <span class="text-slate-400">(opcional)</span></label>
                        <input id="panel-url" type="url" class="modal-input" placeholder="https://empresa.juntify.com" maxlength="255">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" data-close-panel-modal>Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="confirm-create-panel">Crear panel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
